<?php

namespace App\Command;

use App\Helper\BaseHelper;
use App\Repository\BalanceConfigRepository;
use App\Repository\BalancePartnersRepository;
use App\Repository\BalanceProcessRepository;
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
    name: 'balance_finish',
    description: 'php bin/console balance_finish 2024-05-07 --process=0',
)]
class balanceFinishCommand extends Command
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

        $this->logger->info(sprintf("[$commandName] start"));
        //Собираем все процессы в статусе created за дату
        $processId = $this->balanceProcessRepository->findAllCheckedProcess($on_date);

        if (isset($process)) {
            //запустить команду по process
            if ($process > 0) {
                $this->logger->info(sprintf("[$commandName] Запуск команды по опции process=( %s )", $process));
                $processId = $this->balanceProcessRepository->findOneCheckedProcess($process);
            }
        }

        if (sizeof($processId) !== 0) {

            foreach ($processId as $item){
//                dd($item);
                $item->setStatus('finishing');
                $this->entityManager->persist($item);
                $this->entityManager->flush();

                $walletId = $item->getBalanceConfigId()->getWalletId();
                $counterInWas = $item->getCounterInWas();
                $counterInBe = $counterInWas + $item->getCounterInBe();

                $this->logger->info("[$commandName] Пополняем counter_in в процессе [". $item->getId() . "] wallet_id = ". $walletId . ' Было: ' . $counterInWas . ' + '. $item->getCounterInBe() . ' = '. $counterInBe);

                if (in_array($item->getBalanceConfigId()->getType(), ['provider', 'rolling'])) {
                    //Пополнить кошелек провайдера
                    $res = $this->helper->putRequestApi('PUT', 'wallet/inc/'. $walletId, ['type' => 'provider', 'counter_in' => $item->getCounterInBe()]);
                } else {
                    //Пополнить кошелек партнера
                    $res = $this->helper->putRequestApi('PUT', 'wallet/inc/'. $walletId, ['type' => 'internal', 'counter_in' => $item->getCounterInBe()]);
                }

                if (isset($res['error'])){
                    $io->error('Ошибка в отправке '. $res['code'] . ' ' . $res['error']);
                    return Command::FAILURE;
                }

                $item->setStatus('finished');
                $this->entityManager->persist($item);
                $this->entityManager->flush();
            }

            $io->success('Завершили');
            return Command::SUCCESS;
        }

        $this->logger->warning(sprintf("[$commandName] Нет процессов в статусе checked за дату %s", $on_date));
        return Command::SUCCESS;
    }
}
