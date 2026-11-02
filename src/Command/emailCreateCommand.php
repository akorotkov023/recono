<?php

namespace App\Command;

use App\Helper\BaseHelper;
use App\Repository\BalanceConfigRepository;
use App\Repository\BalancePartnersRepository;
use App\Repository\BalanceProcessRepository;
use App\Repository\EmailConfigRepository;
use App\Repository\EmailProcessRepository;
use App\Repository\RegistryCommandRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use http\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'email_create',
    description: 'php bin/console email_create 2024-05-07 --id=0',
)]
class emailCreateCommand extends Command
{
    public function __construct(
        private readonly EmailConfigRepository     $emailConfigRepository,
        private readonly EmailProcessRepository    $emailProcessRepository,
        private readonly RegistryCommandRepository $providerRegistryCommandRepository,
        private readonly BaseHelper                $helper,
        private readonly LoggerInterface           $logger
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('date', InputArgument::REQUIRED, 'Input date')
            ->addOption('id', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');

        $id = $input->getOption('id');

        $this->logger->info(sprintf('[%s] start', $commandName));

        if ($id !== null && (int) $id > 0) {
            $id = (int) $id;

            $this->logger->info(sprintf(
                '[%s] Запуск команды по опции id=(%d)',
                $commandName,
                $id
            ));

            $activeConfigEmail = $this->emailConfigRepository->findOneActive($id);
        } else {
            $activeConfigEmail = $this->emailConfigRepository->findAllActive();
        }
        $allRegistryCommandByDate = $this->providerRegistryCommandRepository->checkVerifiedRegistryForDate($on_date);

        $providerStatuses = [];
        foreach ($allRegistryCommandByDate as $registryCommand) {
            $provider = $registryCommand->getRegistryConfigId()->getProvider();
            $status   = $registryCommand->getStatus();

            if (!isset($providerStatuses[$provider])) {
                $providerStatuses[$provider] = true; // считаем что всё verified, пока не доказано обратное
            }

            if ($status !== 'verified') {
                $providerStatuses[$provider] = false;
            }
        }
//        dd($providerStatuses);
        $cnt = 0;
        $error = 0;
        foreach ($activeConfigEmail as $emailConfig) {
            $providerSlug = $emailConfig->getProvider();

            foreach ($providerSlug as $provider) {
                if (!isset($providerStatuses[$provider])) {
                    $this->logger->warning(sprintf("[$commandName] Нет команд по провайдеру $provider"));
                }
                if ($providerStatuses[$provider] === true) {
                    // ✅ Запускаем создание процесса
                    $processId = $this->emailProcessRepository->findCreatedProcess($emailConfig->getId(), $date);
                    if (sizeof($processId) === 0) {
                        //Создаем процесс за дату если его нет.
                        $this->helper->createEmailProcess($emailConfig, $on_date);
                        $cnt += 1;
                    } else {
                        //Если процесс в этом статусе то не трогаем его.
                        if (!in_array($processId[0]->getStatus(), ['checking','checked','finishing','finished','error'], true)) {

                            $this->helper->deleteProcessForReCreateEmail($processId[0]->getId());
                            $this->helper->createEmailProcess($emailConfig, $on_date);
                            $cnt += 1;
                        }
                    }
                } else {
                    $error += 1;
                    // ❌ Не запускаем — пишем, что провайдер не сверён
                    $this->logger->info("[$commandName] Провайдер - { $provider } не сверен !!! ");
                }
            }
        }
        $this->logger->info(sprintf("-------------------------------------"));
        $this->logger->info(sprintf("[$commandName] Создали процессов = [ $cnt ]"));
        $this->logger->info(sprintf("[$commandName] Не созданных процессов = [ $error ]"));
//        $this->helper->createNewErrorProcessEmail($activeConfigEmail, $date, $error);
        return Command::SUCCESS;
    }
}
