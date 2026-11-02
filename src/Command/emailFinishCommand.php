<?php

namespace App\Command;

use App\Helper\BaseHelper;
use App\Repository\BalancePartnersRepository;
use App\Repository\EmailProcessRepository;
use App\Service\RegistryFileSender;
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
    name: 'email_finish',
    description: 'php bin/console email_finish 2024-05-07 --process=0',
)]
class emailFinishCommand extends Command
{
    public function __construct(
        private EmailProcessRepository $emailProcessRepository,
        private RegistryFileSender $fileSender,

        private BaseHelper $helper,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('date', InputArgument::REQUIRED, 'Input date')
             ->addOption('process', null,InputOption::VALUE_REQUIRED)
            ->addOption('id', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');
        $process = $input->getOption('process');
        $id = $input->getOption('id');

        $this->logger->info(sprintf("[$commandName] start"));
        //Собираем все процессы в статусе checked за дату
        $processId = $this->emailProcessRepository->findAllCheckedProcess($on_date, $id);

//        dd($processId);
        if (isset($process)) {
            //запустить команду по process
            if ($process > 0) {
                $this->logger->info(sprintf("[$commandName] Запуск команды по опции process=( %s )", $process));
                $processId = $this->emailProcessRepository->findOneCheckedProcess($process);
            }
        }

        if (sizeof($processId) !== 0) {
            foreach ($processId as $item) {
                $slug = $item->getEmailConfigId()->getSlug();
                $amounts = $item->getTotalAmount();
                $counts =  $item->getCount();
                $item->setStatus('finishing');
                $this->entityManager->persist($item);
                $this->entityManager->flush();

                //Отправляем реестры по почте
                $files = $item->getRegs();
                $to = $item->getEmailConfigId()->getEmailTo();
                $cc = $item->getEmailConfigId()->getEmailCc();

                if (sizeof($files) !== 0 and sizeof($to) !== 0 and sizeof($cc) !== 0) {
                    $this->logger->info(sprintf("[$commandName] отправка почты: $slug"));

                    $res = $this->fileSender->sendFile($files, $to, $cc, $on_date, $slug, $amounts, $counts);

                    $this->logger->info(sprintf("[$commandName] sendFile = %s", $res));
                    if ($res == 'ok') {
                        $item->setStatus('finished');
                        $this->entityManager->persist($item);
                        $this->entityManager->flush();
                        sleep(2);
                    } else {
                        $error = 'Письмо с реестром не отправлено (ошибка SMTP, подробности в логе приложения)';
                        $io->warning($error);
                        $this->helper->createErrorLogProcessEmail($item, $error);
                        $item->setStatus('error');
                        $this->entityManager->persist($item);
                        $this->entityManager->flush();
                        $this->logger->info(sprintf("[$commandName] %s", $error));
                        return Command::INVALID;
                    }
                } else {
                    $this->logger->info(sprintf("[$commandName] Почта не отправлена по причине (sizeof($files) !== 0 and sizeof($to) !== 0 and sizeof($cc) !== 0)"));
                    return Command::INVALID;
                }
            }

            $this->logger->info(sprintf("[$commandName] Реестры отправлены успешно"));
            return Command::SUCCESS;
        }

        $this->logger->warning(sprintf("[$commandName] Нет процессов в статусе checked за дату %s", $on_date));
        return Command::SUCCESS;
    }
}
