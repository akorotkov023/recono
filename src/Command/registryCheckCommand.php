<?php

namespace App\Command;

use App\Entity\RegistryCommand;
use App\Entity\RegistryConfig;
use App\Enum\RegistryCommandStatusEnum;
use App\Helper\BaseHelper;
use App\Repository\RegistryCommandRepository;
use App\Repository\RegistryConfigRepository;
use App\Repository\RegistryTransactionsRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'registry_check',
    description: 'Проверка реестров за дату: "php bin/console registry_check provider date"',
)]
class registryCheckCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private BaseHelper $helper,
        private RegistryTransactionsRepository $transactionsRepository,
        private RegistryCommandRepository $registryCommandRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Input provider')
            ->addArgument('date', InputArgument::REQUIRED, 'Input date');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $provider = $input->getArgument('provider');
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');

        // Смотрим активные записи в настройках
        $this->logger->info(sprintf("[$commandName] Начинаем проверку "));
        // Поиск команд за дату по провайдеру
        $commands = $this->registryCommandRepository->findAllCommandsByProviderAndDate($provider, $on_date);
//        dd($commands);
        $allCommands = [];
        $all_status = [];
        $flag = false;
        $utc = 'UTC-3';
        foreach($commands as $val){
            $allCommands[] = $val->getId();
            $all_status[] = $val->getStatus();
            $utc = $val->getRegistryConfigId()->getUtc();
        }
        foreach ($all_status as $status) {
            if ($status !== RegistryCommandStatusEnum::VERIFIED) {
                $flag = true;
            }
        }
        if ($flag) {
            $this->logger->info("[$commandName] Есть не сверенные реестры");

            $data = $this->helper->preVerifiedAllOuterTxId($provider, $d, $allCommands, $utc);
//            dd($data);
             foreach ($allCommands as $commandId) {
                 if ( $data['diff_reg'][$commandId] == 0 && $data['diff_base'][$commandId] == 0 ) {
                     $this->helper->verified($commandId);
                     $io->note("verified. Команда: {$commandId}");
                 } else {
                     // diff_reg Есть в реестре нет в базе
                     //1) Charged and 2023-03-08 21:00:34 -> 2023-03-08 20:59:59

                     if ($data['diff_reg'][$commandId] !== 0) {
                         $diff_reg[] = explode(',', $data['diff_reg'][$commandId]);

                         foreach ($diff_reg[0] as $providerId) {
                             $txs = $this->helper->diffReg($providerId);
                             $result = $this->transactionsRepository->findByProviderId($commandId, $providerId);

                             if (!isset($txs->status)) {
                                 $issues = [
                                     'command_id' => $commandId,
                                     'type' => 'diff_reg',
                                     'description' => 'Есть в реестре нет в базе. Смотри Dev',
                                     'processing_id' => '',
                                     'provider_id' => $providerId,
                                     'started' => '',
                                     'status' => 'NotFound'
                                 ];
                                 $this->helper->saveToCollationIssues($issues);
                                 $this->helper->issues($commandId);
                             } else {
                                 if ($txs->status == 'Payout_suspended') {
                                     $this->logger->info(sprintf("[$commandName][preVerified...] Payout_suspended => [%s %s %s %s]", $txs->id, $txs->outertxid, $txs->status, $txs->started ));

                                     $issues = [
                                         'command_id' => $commandId,
                                         'type' => 'diff_reg',
                                         'description' => 'Есть в реестре -> {update_status}',
                                         'processing_id' => $txs->id,
                                         'provider_id' => $txs->outertxid,
                                         'started' => $txs->started,
                                         'status' => $txs->status
                                     ];
                                     // записать в collation_issues
                                     $this->helper->saveToCollationIssues($issues);
                                     $this->helper->issues($commandId);
                                 } elseif ($txs->status == 'refunded') {
                                     $this->logger->info(sprintf("[$commandName][preVerified...] refunded => [%s %s %s %s]", $txs->id, $txs->outertxid, $txs->status, $txs->started ));
                                 } else {
                                     $this->logger->info(sprintf("[$commandName][preVerifiedAll...] %s => [%s %s %s %s]", $txs->status, $txs->id, $txs->outertxid, $txs->status, $txs->started));

                                     $issues = [
                                         'command_id' => $commandId,
                                         'type' => 'diff_reg',
                                         'description' => 'Есть в реестре нет в базе.',
                                         'processing_id' => $txs->id,
                                         'provider_id' => $txs->outertxid,
                                         'started' => $txs->started,
                                         'status' => $txs->status
                                     ];
                                     // записать в collation_issues
                                     $this->helper->saveToCollationIssues($issues);
                                     $this->helper->issues($commandId);
                                 }
                             }
                         }
                     }
                     // diff_base Есть в базе но нет в реестре
                     // переходящая
                     //1) Charged and 2023-03-08 20:58:55 -> 2023-03-08 21:00:09
                     if ($data['diff_base'][$commandId] !== 0) {
                         $diff_base[] = explode(',', $data['diff_base'][$commandId]);
//                    dd($diff_base);
                         foreach ($diff_base[0] as $providerId) {
                             $txs = $this->helper->diffReg($providerId);
//                        dd($txs);
                             if ($txs->status === 'charged') {
                                 $description = 'Переходящая! ' . $txs->status . ' started: ' . $txs->started;
                             } else {
                                 $description = 'Есть в базе но нет в реестре!';
                             }

                             $issues = [
                                 'command_id' => $commandId,
                                 'type' => 'diff_base',
                                 'description' => $description,
                                 'processing_id' => $txs->id,
                                 'provider_id' => $txs->outertxid,
                                 'started' => $txs->started,
                                 'status' => $txs->status
                             ];
//                    dd($issues);
                             // записать в collation_issues
                             $this->helper->saveToCollationIssuesSQL($issues);
                         }
                     }
                     $this->helper->issues($commandId);
                     $this->logger->info("[$commandName] issues. Есть расхождения. Смотри collation_issues ");
                 }
             }

        } else {
            $this->logger->info("[$commandName][end] Все реестры уже сверены. ");
        }
        return Command::SUCCESS;
    }
}
