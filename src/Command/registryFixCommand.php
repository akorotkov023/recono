<?php

namespace App\Command;

use App\Entity\CollationIssues;
use App\Helper\BaseHelper;
use App\Repository\RegistryCommandRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTime;
use DateInterval;


#[AsCommand(
    name: 'registry_fix',
    description: 'Чиним issues из collation_issues',
)]
class registryFixCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private BaseHelper $helper,
        private EntityManagerInterface $entityManager,
        private RegistryCommandRepository $commandRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $this->logger->info(sprintf('[issues...]'));
        $issues = $this->entityManager->getRepository(CollationIssues::class)->findAll();

        $cnt = count($issues);
        if ($cnt < 1150) {
            $io->note("Count Issues = {$cnt} Begin...");
            $i = 0;
            foreach ($issues as $issue){
                // Есть в реестре нет в базе
                if ($issue->getType() == 'diff_reg') {
                    if ( $issue->getStatus() == 'charged' ) {
                        $this->logger->info(sprintf("[$commandName] => %s", $issue->getProcessingId()));
                        $this->logger->info(sprintf("[$commandName] Show problem => %s", $issue->getDescription()));

//                        $this->helper->updateStartedToStarted($issue->getProcessingId());
//                        $this->logger->info(sprintf("[$commandName] fix Done!  %s", $issue->getProcessingId()));
                        // записать в лог
//                        $this->helper->addFixIssueLog($issue);
//                        $this->helper->deleteIssues($issue);
//                        $i+=1;
                    }
                    if ( $issue->getStatus() == 'payout_suspended' ) {
                        $this->logger->info(sprintf("[$commandName] => %s", $issue->getProcessingId()));
                        $this->logger->info(sprintf("[$commandName] Show problem => %s", $issue->getDescription()));
                        // сделать успешной

//                        $status = $this->helper->updateStatus($issue->getProcessingId());
//                        if ($status == 'charged') {
//                            $this->logger->info(sprintf('[issues...] {update_status} fix Done!  %s', $issue->getProcessingId()));
//                            $this->helper->addFixIssueLog($issue);
//                            $this->helper->deleteIssues($issue);
//                            $i+=1;
//                        } else $this->logger->info(sprintf('[issues...] {update_status} Error. Show logs  %s', $issue->getProcessingId()));
                    }
                }
                // Есть в базе нет в реестре
                if ($issue->getType() == 'diff_base') {
                    if ($issue->getStatus() == 'charged') {
                        //переходящая
                        $command = $this->commandRepository->find(['id' => $issue->getCommandId()]);

                        if (!empty($command)){
                            $futureStarted = $this->getTimeDifference($issue->getStarted(), $command->getOnDate());

                            $this->logger->info(sprintf("[$commandName] => %s", $issue->getProcessingId()));
                            $this->logger->info(sprintf("[$commandName] Show problem [diff_base] => %s", $issue->getDescription()));

                            $this->helper->updateStartedToUpdated($issue->getProcessingId(), $futureStarted);
                            $this->logger->info(sprintf("[$commandName] fix Done!  %s", $issue->getProcessingId()));
                            $this->helper->addFixIssueLog($issue);
                            $this->helper->deleteIssues($issue);
                            $i+=1;
                        }
                    }
                }
            }
            $io->note("Fix count = $i");

        } else $io->note("Count Collation Issues > $cnt something wrong!");

        return Command::SUCCESS;
    }

    function getTimeDifference(DateTime $from, DateTime $to): DateTime
    {
        $to->setTime(21, 0, 0);
        $diffInSeconds = $to->format('U.u') - $from->format('U.u'); // разница в секундах (float)
        $minutes = $diffInSeconds / 60;
        $diffTime = ceil($minutes);

        $interval = new DateInterval("PT".$diffTime."M"); // 30 минут
        $from->add($interval);

        return $from;
    }
}
