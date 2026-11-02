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
    name: 'balance_create',
    description: 'php bin/console balance_create 2024-05-07 --id=0',
)]
class balanceCreateCommand extends Command
{
    public function __construct(
        private BalanceConfigRepository $balanceConfigRepository,
        private BalanceProcessRepository $balanceProcessRepository,
        private RegistryCommandRepository $providerRegistryCommandRepository,
        private BalancePartnersRepository $balancePartnersRepository,

        private BaseHelper $helper,
        private LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('date', InputArgument::REQUIRED, 'Input date')
            ->addOption('id', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');
        $configId = $input->getOption('id');

        $this->logger->info(sprintf("[$commandName] start"));
        $activeConfigBalance = $this->balanceConfigRepository->findAllActive();

        if (isset($configId)) {
            if ($configId > 0) {
                $this->logger->info(sprintf("[$commandName] Запуск процесса по configId = %s", $configId));
                $activeConfigBalance = $this->balanceConfigRepository->findOneActive($configId);
            }
        }

        $io->note('Проверка, реестры успешно сверены за эту дату?');
        $res = $this->providerRegistryCommandRepository->checkVerifiedRegistryForDate($on_date);
//        dd($res);
        if (sizeof($res) !== 0) {
            $cnt = false;
            foreach ($res as $command) {
                if ($command->getStatus() <> 'verified'){
                    $cnt = true;
                }
            }
            if (!$cnt) {
                $io->success('Ok');
                $this->logger->info("[$commandName] Проверка, сохранились ли балансы в balance_partners за дату " . $on_date);

                $this->logger->info("[$commandName] ОК ");
                $cnt = 0;

                foreach ($activeConfigBalance as $id => $row) {
                    $this->logger->info(sprintf("[$commandName] Смотрим %s", $row->getPartnerName()));
                    $partnerIdBalance = $this->balancePartnersRepository->getPartnerIdBalanceForDate($row->getPartnerId(), $on_date);
//                        dd($partnerIdBalance);
                    if (sizeof($partnerIdBalance) === 0) {
                        $row->counterInWas = 0;
                    } else {
                        $row->counterInWas = (integer)$partnerIdBalance[0]->getCounterIn();
                    }
//                        dd($row);
                    // Собираем суммы amount и fee для балансов
                    if ($row->getType() == 'provider') {
                        $resSum = $this->helper->getSumFutureCounterInProvider($row->getWalletId(), $on_date);
                    } elseif ($row->getType() == 'rolling') {
                        $resSum = $this->helper->getSumFutureCounterInProviderRolling($row->getProvider(), $on_date);
                    } else {
                        $resSum = $this->helper->getSumFutureCounterIn($row->getPartnerId(), $on_date);
                    }
//                        dd($resSum->fee);
                    if ($resSum->sum === null) {
                        $row->totalAmount = 0;
                        $row->counterInBe = 0;
                    } else {
                        $row->totalAmount = $resSum->sum;
                        $row->counterInBe = (int)$resSum->fee;
                    }

                    $processId = $this->balanceProcessRepository->findCreatedProcess($row->getId(), $date);
//                        dd($processId);
                    if (sizeof($processId) === 0) {
                        //Создаем процесс за дату если его нет.
                        $this->helper->createBalanceProcess($row, $on_date);
                        $cnt += 1;
                    } else {
                        //Если процесс в этом статусе то не трогаем его.
                        if (!in_array($processId[0]->getStatus(), ['checking','checked','finishing','finished','error'], true)) {

                            $this->helper->deleteProcessForReCreate($processId[0]->getId());
//                                dd($counterInWas);
                            $this->helper->createBalanceProcess($row, $on_date);
                            $cnt += 1;
                        }
                    }
                }

                $this->logger->info("[$commandName] Создали процессов = [ $cnt ]");
                $this->logger->info("[$commandName] end");
                return Command::SUCCESS;

            } else {
                $error = 'Есть не сверенные реестры за дату ' . $on_date. ' Смотри Меню -> Сверка -> Сверка реестров';
                $io->warning($error);
                $this->helper->createNewErrorProcess($activeConfigBalance, $date, $error);
                $this->logger->info("[$commandName] end");
                return Command::SUCCESS;
            }
        }
        $error = 'Нет реестров за дату '. $on_date;
        $io->warning($error);
        $this->helper->createNewErrorProcess($activeConfigBalance, $date, $error);
        $this->logger->info("[$commandName] end");
        return Command::SUCCESS;
    }
}

