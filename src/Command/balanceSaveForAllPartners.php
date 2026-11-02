<?php

namespace App\Command;

use App\Entity\BalancePartners;
use App\Helper\BaseHelper;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'balanceSaveForAllPartners',
    description: 'Команда которая собирает балансы партнеров и сохраняет их в базу',
)]
class balanceSaveForAllPartners extends Command
{
    public function __construct(
        private BaseHelper $helper,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    )
    {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $count = 0;
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));

            $this->logger->info("[$commandName] Получаем балансы");
            $balances = $this->helper->getRequestApi('GET', 'wallet?env=prod');
            $cnt = count($balances);
            $this->logger->info("[$commandName] Получено $cnt шт.");
            $this->logger->info("[$commandName] Получаем балансы провайдера");
            $provider_balances = $this->helper->getRequestApi('GET', 'wallet?env=prod&type=provider');
            $cnt_provider_wallet = count($provider_balances);
            $this->logger->info("[$commandName] Получено $cnt_provider_wallet шт.");
            $this->logger->info("[$commandName] Получаем партнёров");
            $partners = $this->helper->getRequestApi('GET', 'profile?is_active=true');
            $cnt_partners = count($partners);
            $this->logger->info("[$commandName] Получено $cnt_partners шт.");

            //TODO Сделать отправку уведомления если балансы не собраны

            $result = [];
            foreach ($partners as $key => $value) {
                $wallet = [];
                $value->wallet = $wallet;
                $value->balance = 0;
                $value->counter_out_old = 0;
                $value->counter_out_calculated = 0;
                $value->counter_in = 0;
                $value->credit = 0;
                $value->type = 'none';
                for ($i = 0; $i < count($balances); $i++) {
                    if ((int)$value->id === (int)$balances[$i]->partner_id ) {
                        $wallet[] = $balances[$i];
                        if ($balances[$i]->env === 'prod') {
                            $value->balance = $balances[$i]->balance;
                            $value->counter_out_old = $balances[$i]->counter_out_old;
                            $value->counter_out_calculated = $balances[$i]->counter_out_calculated;
                            $value->counter_in = $balances[$i]->counter_in;
                            $value->credit = $balances[$i]->credit;
                            $value->type = $balances[$i]->type;
                        }
                    }
                }
                $value->wallet = $wallet;
                $result[] = $value;
            }

//            dd(($result));
            foreach ($provider_balances as $key => $value) {
                if ($value->env === 'prod') {
                    $value->id = $value->id + 1000;
                    $result[] = $value;
                }
            }

            foreach ($result as $item) {
                $balancePartners = new BalancePartners();
                $balancePartners->setPartnerId($item->id);
                $balancePartners->setName($item->name);
                $balancePartners->setCounterIn($item->counter_in);
                $balancePartners->setCounterOutOld($item->counter_out_old);
                $balancePartners->setCounterOutCalculated($item->counter_out_calculated);
                $balancePartners->setCredit($item->credit);
                $balancePartners->setBalance($item->balance);
                $balancePartners->setCreatedAt($date);
                $balancePartners->setType($item->type);

                $this->em->persist($balancePartners);
                $count += 1;
            }
            $this->em->flush();

            $this->logger->info("[$commandName] Сохранили в базу = [ $count ] записей");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $file = $e->getFile();
            $this->logger->error("[$commandName] Что то пошло не так! $message ");
            $this->logger->error("[$commandName] Ошибка в файле! $file ");
        }
        $this->logger->info("[$commandName] Балансы собраны! =)");

        return Command::SUCCESS;
    }
}

