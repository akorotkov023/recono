<?php

namespace App\Command;

use App\Entity\BalancePartners;
use App\Helper\BaseHelper;
use App\Repository\BalanceConfigRepository;
use App\Repository\BalancePartnersRepository;
use App\Repository\BalanceProcessRepository;
use App\Repository\RegistryCommandRepository;
use DateTime;
use DateTimeZone;
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
    name: 'balance_partner_reload',
    description: 'Обновляем табличку balance_partners для одного партнера за нужную дату по процессу php bin/console balance_partner_reload 2024-07-16 --process=73',
)]
class balanceReloadForOnePartner extends Command
{
    public function __construct(
        private BalanceProcessRepository $balanceProcessRepository,
        private BalancePartnersRepository $balancePartnersRepository,

        private BaseHelper $helper,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void {
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
        $partnerId = null;

        if (isset($process)) {

            $processId = $this->balanceProcessRepository->findOneReloadProcess($process);

//            dd($processId);

            $this->logger->info(sprintf("[$commandName] start"));
            if (sizeof($processId) !== 0) {

                $partnerId = $processId[0]->getBalanceConfigId()->getPartnerId();
                $baseBalance = $this->balancePartnersRepository->getPartnerIdBalanceForDate($partnerId, $on_date);

//              dd(count($baseBalance));
                if (in_array($processId[0]->getBalanceConfigId()->getType(), ['provider', 'rolling'])) {
                    $id = $processId[0]->getBalanceConfigId()->getWalletId();
                    $balance[] = $this->helper->getRequestApiNoPage('GET', "wallet/$id?type=provider");
                } else {
                    $id = $processId[0]->getBalanceConfigId()->getWalletId();
                    $balance[] = $this->helper->getRequestApiNoPage('GET', "wallet/$id");
                }

//              dd(($balance[0]->counter_in));
//              dd($baseBalance);

                if (count($balance) === 1 && count($baseBalance) > 0) {
                    $this->logger->info(sprintf("[$commandName] Смотрим counter_in and getCounterIn"));
                    $this->logger->info(sprintf("[$commandName] %s and %s", $balance[0]->counter_in, (int)$baseBalance[0]->getCounterIn()));
                    if ($balance[0]->counter_in !== (int)$baseBalance[0]->getCounterIn()) {
                        $this->logger->info(sprintf("[$commandName] Сохраняем новые значения"));
                        $baseBalance[0]->setCounterIn($balance[0]->counter_in);
                        $baseBalance[0]->setCredit($balance[0]->credit);
                        $baseBalance[0]->setBalance($balance[0]->balance);
                        $processId[0]->setStatus('new');
                        $this->entityManager->persist($baseBalance[0]);
                        $this->entityManager->persist($processId[0]);
                        $this->entityManager->flush();
                        $this->logger->info("[$commandName] Баланс обновлен.");
                    } else {
                        $this->logger->info(sprintf("[$commandName] Данные равны, ничего не делаем"));
                    }
                } else {
                    $this->logger->info(sprintf("[$commandName] Условие не отработало корректно (count($balance) === 1 && count($baseBalance) > 0)(%s)", $process));
                }
            } else {
                $this->logger->info(sprintf("[$commandName] Процесс не найден (%s)", $process));
            }
        }
        $this->logger->info("[$commandName] end");
        return Command::SUCCESS;
    }
}

