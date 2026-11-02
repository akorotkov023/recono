<?php

namespace App\Command;

use App\Helper\BaseHelper;
use App\Repository\BalancePartnersRepository;
use App\Repository\EmailConfigRepository;
use App\Repository\EmailProcessRepository;
use App\Repository\RegistryCommandRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'email_cancel',
    description: 'php bin/console email_cancel 2024-05-07 --process=0',
)]
class emailCancelCommand extends Command
{
    public function __construct(
        private EmailConfigRepository $emailConfigRepository,
        private EmailProcessRepository $emailProcessRepository,
        private RegistryCommandRepository $providerRegistryCommandRepository,
        private BalancePartnersRepository $balancePartnersRepository,

        private BaseHelper $helper,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('date', InputArgument::REQUIRED, 'Input date')
            ->addOption('process', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');

        $process = $input->getOption('process');

        $this->logger->info(sprintf("[$commandName] start"));
        //Собираем все процессы в статусе created за дату
        $processId = $this->emailProcessRepository->findAllCancelProcess($on_date);

        if (isset($process)) {
            //запустить команду по process
            if ($process > 0) {
                $this->logger->info(sprintf("[$commandName] Запуск команды по опции process=( %s )", $process));
                $processId = $this->emailProcessRepository->findOneCancelProcess($process);
            }
        }

//        dd($processId);

        if (sizeof($processId) !== 0) {
            foreach ($processId as $item) {

                $this->entityManager->remove($item);
                $this->entityManager->flush();

                $this->logger->info(sprintf("[$commandName] Процесс отменен успешно * %s *", $process));

            }

            $this->logger->info(sprintf("[$commandName] Процесс отменен успешно"));
            return Command::SUCCESS;
        }

        $this->logger->warning(sprintf("[$commandName] Нет процессов в статусе not in (finished) за дату %s", $on_date));
        return Command::SUCCESS;

    }
}
