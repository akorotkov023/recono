<?php

namespace App\Command;


use App\Helper\BaseHelper;
use App\Repository\BalanceProcessRepository;
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
    name: 'balance_check',
    description: 'php bin/console balance_check 2024-05-07 --process=0',
)]
class balanceCheckCommand extends Command
{
    public function __construct(
        private BalanceProcessRepository $balanceProcessRepository,

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
            ->addOption('process', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');

        $process = $input->getOption('process');

        $this->logger->info("[$commandName] start");
        //Собираем все процессы в статусе created за дату
        $processId = $this->balanceProcessRepository->findAllCreatedProcess($on_date);

        if (isset($process)) {
            //запустить команду по process
            if ($process > 0) {
                $this->logger->info(sprintf("[$commandName] Запуск команды по опции process=( %s )", $process));
                $processId = $this->balanceProcessRepository->findOneCreatedProcess($process);
            }
        }

        if (sizeof($processId) !== 0) {

//            $io->note('Проверка totalAmount');
//
//            foreach ($processId as $item){
//                $item->setStatus('checking');
//                $this->entityManager->persist($item);
//                $this->entityManager->flush();
//
//                $partnerId = $item->getBalanceConfigId()->getPartnerId();
//                $io->note('Установка checking процессу '. $item->getId() . ' Партнеру '. $partnerId);
//
//                $resSum = $this->helper->getFutureCounterInTransactions($partnerId, $on_date);
//
//                foreach ($resSum as $id){
//                    $trOnDate = $this->transactionsRepository->findVerifiedStatusTransaction($id['outertxid']);
//                    if (sizeof($trOnDate) > 0){
//                        $io->info('Подтверждена реестром = '. $id['outertxid']);
//                    } else {
//                        $error = 'Не подтверждена реестром '. $id['outertxid'];
//                        $io->warning($error);
//                        $this->helper->createErrorLogProcess($item, $error);
//                        $item->setStatus('error');
//                        $this->entityManager->persist($item);
//                        $this->entityManager->flush();
//                        return Command::FAILURE;
//                    }
//                }
//            }
//            $io->success('ок');
            $io->note('Проверка counterInWas');

            foreach ($processId as $item){
//                dd($item);
                if (in_array( $item->getBalanceConfigId()->getType(), ['provider', 'rolling'])) {
                    $urlAndWalletId = 'wallet/' . $item->getBalanceConfigId()->getWalletId() . '?type=provider';
                } else {
                    $urlAndWalletId = 'wallet/' . $item->getBalanceConfigId()->getWalletId();
                }
                $counterInWasInProcess = $item->getCounterInWas();
                $io->note('GET /api/v1/' . $urlAndWalletId);

                $balances = $this->helper->getRequestApiNoPage('GET', $urlAndWalletId);

                if (!empty($balances)) {
                    $counterIn = $balances->counter_in;
                    $io->note('if (' . $counterInWasInProcess. ' ? ' . $counterIn . ')');
                    if ($counterInWasInProcess === $counterIn) {
                        $io->success('ок');
                        $item->setStatus('checked');
                    } else {
                        $error = "[$commandName] Проверка кошелька не прошла! Было: ". $counterInWasInProcess . ' не равно  counter_in: ' . $counterIn;
                        $io->warning($error);
                        $this->helper->createErrorLogProcess($item, $error);
                        $item->setStatus('error');
                    }
                    $this->entityManager->persist($item);
                    $this->entityManager->flush();
                } else {
                    $io->warning("[$commandName] Не найден баланс (" . $urlAndWalletId . ") Проверьте настройки в Меню -> Балансы -> Настройки");
                }

            }

            $io->success('Завершили');
            return Command::SUCCESS;
        }
        $io->warning("[$commandName] Нет процессов в статусе created за дату ". $on_date);
        return Command::SUCCESS;
    }
}
