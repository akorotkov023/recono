<?php


namespace App\Helper;


use App\DTO\RegistryTransactionDiff;
use App\Entity\BalanceErrorLog;
use App\Entity\BalanceProcess;
use App\Entity\CollationIssues;
use App\Entity\CollationIssuesLog;
use App\Entity\EmailErrorLog;
use App\Entity\EmailProcess;
use App\Entity\RegistryCommand;
use App\Entity\RegistryConfig;
use App\Entity\RegistryErrorLog;
use App\Entity\RegistryTransactions;
use App\Enum\RegistryCommandStatusEnum;
use App\Repository\BalanceConfigRepository;
use App\Repository\BalanceProcessRepository;
use App\Repository\CollationIssuesLogRepository;
use App\Repository\CollationIssuesRepository;
use App\Repository\EmailConfigRepository;
use App\Repository\EmailProcessRepository;
use App\Repository\RegistryCommandRepository;
use App\Repository\RegistryTransactionsRepository;
use App\Service\ApiSession;
use App\Service\Connection\Manager;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use JetBrains\PhpStorm\Pure;
use Money\Currency;
use Money\Money;
use PDO;
use Psr\Log\LoggerInterface;
use SplFileObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BaseHelper
{
    private BalanceProcessRepository $balanceProcessRepository;
    private EmailProcessRepository $emailProcessRepository;
    private ObjectManager $entityManagerMain;

    public function __construct(
        private LoggerInterface $logger,
        private Manager $singleConnection,
        private EntityManagerInterface $entityManager,
        private string $app,
        private string $env,
        private string $regProvider,
        private string $processing_url,
        private ApiSession $apiSession,
        private HttpClientInterface $client,
        private CollationIssuesRepository $issuesRepository,
        private RegistryCommandRepository $commandRepository,
        private RegistryTransactionsRepository $transactionsRepository,
        private EmailConfigRepository $emailConfigRepository,
        private BalanceConfigRepository $balanceConfigRepository,
        BalanceProcessRepository $balanceProcessRepository,
        EmailProcessRepository $emailProcessRepository,
        ManagerRegistry $doctrine

    )
    {
        $this->balanceProcessRepository = $balanceProcessRepository;
        $this->emailProcessRepository = $emailProcessRepository;
        $this->entityManagerMain = $doctrine->getManager();
    }

    public function dataHelper($data): string
    {
        $helper = '';

        if (!empty($data['start']) && !empty($data['end'])) {
            $helper .= "AND date(t.transaction_date::timestamp) BETWEEN :start AND :end";
        } elseif (!empty($data['start'])) {
            $helper .= "AND date(t.transaction_date::timestamp) >= :start";
        } elseif (!empty($data['end'])) {
            $helper .= "AND date(t.transaction_date::timestamp) <= :end";
        }

        return $helper;
    }

    //Info
    public function getCreated( string $start, string $end ): array
    {
        $sql = /** @lang PostgreSQL */ "
            select id, txid, outertxid, type, partner_id, status, provider, current_terminal, started, amount, error_description
            from txs 
            where status in ('created', 'Charging', 'Payout_suspended')
            and started>='$start' and started < '$end' order by started limit 100
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchAll();
        } else {
            return [];
        }
    }

    //registry extracted
    public function saveCommandAndTransaction( string $tempDirAndFileName, RegistryConfig $config, RegistryCommand $commandId): string
    {
//        dd($tempDirAndFileName);
        $file = new SplFileObject($tempDirAndFileName);
        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl(';');
        $file->seek($file->getSize());
        $linesTotal = $file->key();
        $fileName = $file->getFilename();

//       dd($commandId);
        $this->logger->info(sprintf('[Extracting...] Создаем новый реестр в базе ' . $fileName));
        if ($commandId){
            // перезаписываем данные
            $this->deleteRegistryTransactions( $commandId->getId() );
            $this->saveRegistryTransactions($file, $fileName, $tempDirAndFileName, $linesTotal, $config);
        } else {

            // записываем новые данные
            $this->saveRegistryTransactions($file, $fileName, $tempDirAndFileName, $linesTotal, $config);
        }
        return 'ok';
    }

    //check refunded amount in registry
    public function getRefunded( string $start, string $end ): array
    {
        $sql = /** @lang PostgreSQL */ "
            select to_char(started::timestamp at time zone 'utc' at time zone 'UTC-3', 'YYYY-MM-DD') as date,
                sum(amount), count(id)
            from txs
            where status in ('refunded')
            and started>='$start' and started < '$end'
            group by 1
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return [];
        }
    }

    public function createNewRegistryCommand(RegistryConfig $config, $onDate ): array
    {
        $date = new DateTime('now', new DateTimeZone("+3"));

        // Парсер имени файла
        $fileName = $config->getSlug() . $onDate. '.csv';
//        dd($fileName);
        $d = new DateTime($onDate,  new DateTimeZone("+0"));

        // Создание команды статус: new
        try {
            $commandId = new RegistryCommand();
            $commandId->setRegistryConfigId($config);
            $commandId->setStatus(RegistryCommandStatusEnum::NEW);
            $commandId->setTotalAmount(0);
            $commandId->setTotalCount(0);
            $commandId->setDescription($fileName);
            $commandId->setCreatedAt($date);
            $commandId->setOnDate($d);

            $this->entityManager->persist($commandId);
            $this->entityManager->flush();

            $this->logger->info(sprintf('[Create...] Создалась команда id: %s', $commandId->getId()));

        } catch (\Exception $e) {
            echo ($e->getMessage());
        }

        return [];
    }

    public function saveRegistryTransactions( SplFileObject $file, string $fileName, string $tempDirAndFileName, int $linesTotal, RegistryConfig $config ): array
    {
        $date = new DateTime('now', new DateTimeZone("+3"));
        $res = mb_substr(mb_substr($fileName, -14), 0, -4);
        $d = new DateTime($res,  new DateTimeZone("+0"));

        $this->logger->info(sprintf('[Extracting...] Имя: %s', $tempDirAndFileName));
        $this->logger->info(sprintf('[Extracting...] Дата для реестра: %s', $res));

        //Создание команды
        try {
            $commandId = new RegistryCommand();
            $commandId->setRegistryConfigId($config);
            $commandId->setStatus(RegistryCommandStatusEnum::NEW);
            $commandId->setTotalAmount(0);
            $commandId->setTotalCount(0);
            $commandId->setDescription($fileName);
            $commandId->setCreatedAt($date);
            $commandId->setOnDate($d);

            $this->entityManager->persist($commandId);
            $this->entityManager->flush();

            $this->logger->info(sprintf('[Extracting...] Создалась команда id: %s', $commandId->getId()));

        } catch (\Exception $e) {
            echo ($e->getMessage());
        }

        $countFirstRowIgnore = 1;
        $countLastRowIgnore = 0;
        $totalAmount = new Money(0, new Currency('RUB'));
        $totalCount = 0;

        //проверка на столбцы
        $rowsFile = [];
        foreach ($file as $idx => $rows) {
            $rowsFile[] = $rows;
        }
        $origCols = ['type', 'processing_id', 'provider_id', 'amount', 'fee', 'cardnumber', 'approve_code', 'transaction_date'];
        $diff = array_diff($rowsFile[0], $origCols);

        if (count($diff) === 0) {
            foreach ($file as $idx => $rows) {
                if ($this->needSkip($idx, $rows, $countFirstRowIgnore, $countLastRowIgnore, $linesTotal)) {
                    continue;
                }
                try {
                    //type;processing_id;provider_id;amount;fee;cardnumber;approve_code;transaction_date
                    $transaction = new RegistryTransactions();
                    $transaction->setProcessingId(trim($rows[1]));
                    $transaction->setProviderId(trim($rows[2]));
                    $transaction->setAmount(intval(trim($rows[3])));
                    $transaction->setFee(intval(trim($rows[4])));
                    $transaction->setCardnumber(trim($rows[5]));
                    $transaction->setType(trim($rows[0]));
                    $transaction->setApproveCode(trim($rows[6]));
                    $transaction->setTransactionDate(new DateTime(trim($rows[7]), new DateTimeZone("+3")));
                    if (isset($commandId)) {
                        $transaction->setCommandId($commandId);
                    }

                    $this->entityManager->persist($transaction);

                } catch (\Exception $e) {
                    echo($e->getMessage());
                }

                $amount = new Money(intval(trim($rows[3])), new Currency('RUB'));
                $totalAmount = $totalAmount->add($amount);
                $totalCount = $totalCount + 1;

            }

            if (isset($commandId)) {
                $commandId->setTotalAmount($totalAmount->getAmount());
                $commandId->setTotalCount($totalCount);
                $commandId->setStatus(RegistryCommandStatusEnum::EXTRACTED);
            }

            $this->entityManager->flush();

            $this->logger->info(sprintf('[Extracted] Готов:  %s  =>  ИТОГО: %s  &  TotalCount: %s', $fileName, (string)$totalAmount->getAmount(), $totalCount));

        } else {
            $error = "[Extracting...error] Реестр не соответствует шаблону. Столбец -> $diff[0] в файле {$fileName}";
            $this->logger->info($error);
            echo $error . "\n";
            //закинуть в ошибки
            if (isset($commandId)) {
                $this->createErrorLogCommand($commandId, $error);
                $commandId->setTotalAmount($totalAmount->getAmount());
                $commandId->setTotalCount($totalCount);
                $commandId->setStatus(RegistryCommandStatusEnum::ERROR);
            }
            $this->entityManager->flush();

            return [];
        }

        //переместить файл в нужное место
        $moveTo = $this->regProvider;
        rename($tempDirAndFileName, $moveTo . $fileName);
        $this->logger->info(sprintf('[Extracted] Перенос файла: Ок'));

        return [];
    }

    public function deleteRegistryTransactions( int $id ): array
    {
        $sql1 = /** @lang PostgreSQL */
            "
        delete from reconciliation.registry_transactions where command_id = '{$id}';
        ";

        $sql2 = /** @lang PostgreSQL */
            "
        delete from reconciliation.registry_command where id = '{$id}';
        ";
        try {
            $stmt = $this->entityManager->getConnection()->prepare($sql1);
            $stmt->executeQuery();
            $stmt = $this->entityManager->getConnection()->prepare($sql2);
            $stmt->executeQuery();
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return [];
    }

    public function needSkip(int $idx, array $row, int $countFirstRowIgnore, int $countLastRowIgnore, int $linesTotal): bool
    {
        $flag = false;

        if ($this->skipFirstLines($idx, $countFirstRowIgnore)) {
            $flag = true;
        } elseif ($this->skipLastLines($idx, $countLastRowIgnore, $linesTotal)) {
            $flag = true;
        } elseif ($this->skipNullLine($row)) {
            $flag = true;
        }

        return $flag;
    }

    private function skipFirstLines(int $idx, $config): bool
    {
        return ($idx < $config && $config > 0);
    }

    private function skipLastLines(int $idx, $countLastRowIgnore, int $linesTotal): bool
    {
        return ($idx >= $linesTotal - $countLastRowIgnore && $countLastRowIgnore > 0);
    }

    private function skipNullLine(array $row)
    {
        return is_null($row[0]);
    }

    //registry verified
    public function preVerified($provider, $date, $id, $bank): array
    {
        //TODO переделать это всё
        $command = $this->commandRepository->find($id);
        if ($command) {
            if (str_contains($command->getRegistryConfigId()->getSlug(), 'payout')) {
                $type = 'payout';
            } else $type = 'payment';
            $regId = $this->transactionsRepository->findByCommandIdProviderId($id);
            $utc = $command->getRegistryConfigId()->getUtc();

            $sql = /** @lang PostgreSQL */
                "
            select
                t.outertxid as provider_id,
                t.amount::int as base_amount,
                1 as base_count
            from txs t join partners p on t.partner_id = p.id
            where 
                t.started >= date ('$date') at time zone 'utc' at time zone '$utc' and
                t.started < date ('$date') at time zone 'utc' at time zone '$utc' + interval '1 day'
                and lower(t.status) in ('charged', 'refunded') and t.env = 'prod'
                and t.type in ('$type') and provider in ('$provider')
            ";

            $conn = $this->singleConnection->getConnection($this->app);
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $base_res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cnt = count($base_res);

            $baseAmount = new Money(0, new Currency('RUB'));

            $baseId = [];
            foreach ($base_res as $row) {
                $baseId[] = $row['provider_id'];
                $amount = new Money((int)$row['base_amount'], new Currency('RUB'));
                $baseAmount = $baseAmount->add($amount);
            }

//            $reg = array_diff($baseId, $regId);
            $reg = array_diff($regId ?? 0, $baseId ?? 0);

//            dd($reg, $regId);

            foreach ($reg as $transactions) {
                $str[] = "'$transactions'";
            }

            $diffReg = !empty($str) ? implode(",", $str) : 0;

            $base = array_diff($baseId ?? 0, $regId ?? 0);

            foreach ($base as $trans) {
                $str2[] = "'$trans'";
            }

            $diffBase = !empty($str2) ? implode(",", $str2) : 0;

            return [
                'reg_name'    => $command->getDescription(),
                'provider'    => $provider,
                'on_date'     => $date,
                'reg_amount'  => $command->getTotalAmount(),
                'reg_count'   => $command->getTotalCount(),
                'base_amount' => (int)$baseAmount->getAmount(),
                'base_count'  => $cnt,
                'diff_reg'    => $diffReg,
                'diff_base'   => $diffBase,
            ];
        } return [
            'reg_name'    => '',
            'provider'    => '',
            'on_date'     => $date,
            'reg_amount'  => 0,
            'reg_count'   => 0,
            'base_amount' => 0,
            'base_count'  => 0,
            'diff_reg'    => 0,
            'diff_base'   => 0,
        ];
    }

    public function preVerifiedAllOuterTxId($provider, $date, array $allCommands, string $utc): array
    {
        $diff = new RegistryTransactionDiff();
        $regId = [];

        foreach ($allCommands as $command) {
            $regId[$command] = $this->transactionsRepository->findAllByCommandIdProviderId($diff, $command);
        }

        //Запрос для всех реестров по outertxid
        $sql = /** @lang PostgreSQL */ "
            select
                outertxid as provider_id
            from txs
            where 
                started >= date ('$date') at time zone 'utc' at time zone '$utc' and
                started < date ('$date') at time zone 'utc' at time zone '$utc' + interval '1 day'
                and lower(status) = 'charged' and env = 'prod'
                and provider in ('$provider')
                ";
        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $base_res = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($base_res as $row) {
            $diff->addBaseId((string)$row);
        }

        $regIdList = [];
        $baseIdList = [];
        foreach ($regId as $key => $item) {
            $regIdList[$key] = $item->getRegIds();
            $baseIdList[$key] = $diff->getBaseIds();
        }

        // Разница
        $diffReg = [];
        $diffBase = [];
        foreach ($baseIdList as $key => $item){
            $diffReg[$key] = array_diff($regIdList[$key], $item);   // В Registry, но не в базе
            $diffBase[$key] = array_diff($item, $regIdList[$key]);  // В базе, но не в Registry
        }

        // Формируем строки
        $diffRegStr = [];
        $diffBaseStr = [];
        foreach ($diffReg as $key => $item){
            $diffRegStr[$key] = !empty($item)
                ? implode(",", array_map(fn($res) => "'$res'", $item))
                : 0;
        }

        foreach ($diffBase as $key => $item) {
            $diffBaseStr[$key] = !empty($item)
                ? implode(",", array_map(fn($res) => "'$res'", $item))
                : 0;
        }

        return [
            'diff_reg'  => $diffRegStr,
            'diff_base' => $diffBaseStr,
        ];
    }


    public function verified($id): bool
    {
        $command = $this->commandRepository->find($id);

        $this->commandRepository->add($command->setStatus(RegistryCommandStatusEnum::VERIFIED));

        return true;
    }

    //registry issues
    public function issues($id): bool
    {
        $command = $this->commandRepository->find($id);

        $this->commandRepository->add($command->setStatus('issues'));

        return true;
    }

    public function deleteIssues($issues): bool
    {
        try {
            $this->issuesRepository->remove($issues);
        } catch (OptimisticLockException | ORMException $e) {
            $this->logger->warning('deleteIssues error exception!');
        }

        return true;
    }

    public function addFixIssueLog($issues): bool
    {
        $date = new DateTime('now', new DateTimeZone("+3"));

        $collationLog = new CollationIssuesLog();

        $collationLog->setCommandId($issues->getCommandId());
        $collationLog->setCreatedAt($date);
        $collationLog->setProcessingId($issues->getProcessingId());
        $collationLog->setProviderId($issues->getProviderId());
        $collationLog->setDescription('Form. ' . $issues->getDescription());

        $this->entityManager->persist($collationLog);
        $this->entityManager->flush();

        return true;
    }

    public function diffReg($ids): object|null
    {
        //Поиск по outertxid
        $sql = /** @lang PostgreSQL */ "
            select id, outertxid, status, started, updated_at from txs where outertxid in ({$ids})
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchObject();
        if ($res)
            return $res;
        else
            return null;
    }

    public function diffRegRrn($ids): object|null
    {
        //Поиск по rrn (id провайдера)
        $sql = /** @lang PostgreSQL */ "
            select id, rrn as outertxid, status, started, updated_at from txs where rrn in ($ids)
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchObject();
        if ($res)
            return $res;
        else
            return null;
    }

    public function diffRegId($ids): object|null
    {
        if ($ids === "''") {
            return null;
        }
        //Поиск по id
        $sql = /** @lang PostgreSQL */ "
            select id, outertxid, status, started, updated_at from txs where id in ({$ids})
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchObject();


        if ($res)
            return $res;
        else
            return null;

    }

    public function updateStartedToStarted($id): array
    {
        //Запрос для провайдера
        $sql = /** @lang PostgreSQL */ "
            update txs set started = started - '00:02:00'::time where id in ('{$id}')
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return [];
    }

    public function updateStartedToUpdated($id, DateTime $futureStarted): array
    {
        $futureStarted = $futureStarted->format('Y-m-d H:i:s.u T');
        $sql = /** @lang PostgreSQL */ "
            UPDATE public.txs SET started = '$futureStarted' WHERE id = '$id';
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return [];
    }

    public function updateStatus($id): string
    {
        $token = $this->apiSession->updateStatusSession('POST');

        try {
            $response = $this->client->request('POST', $this->processing_url . '/api/v1/transactions/update_status/' . trim($id),
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'auth_bearer' => $token
                ]);
            $data = json_decode($response->getContent(), true);
            $this->logger->info($response->getContent());
            return $data['status'];

        } catch (\Throwable $exception) {
            $this->logger->info($id);
            $this->logger->error($exception->getMessage());
            return 'error';
        }
    }

    public function setStatus($id, $status, $description): string
    {
        $token = $this->apiSession->updateStatusSession('POST');

        try {
//            dd($token, $id, $status, $description, $this->processing_url);
            $response = $this->client->request('POST', $this->processing_url . '/api/v1/transactions/set_status/' . $id,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'auth_bearer' => $token,
                    'json' => ['status' => $status, 'error_description' => $description]
                ]);
            $data = json_decode($response->getContent(), true);
            $this->logger->info($response->getContent());
            return $data['status'] ?? 'error';

        } catch (\Throwable $exception) {
            $this->logger->info($id);
            $this->logger->error($exception->getMessage());
            return 'error';
        }
    }

    public function renotify($id): bool
    {
        $token = $this->apiSession->updateStatusSession('POST');

        try {
            $response = $this->client->request('POST', $this->processing_url . '/api/v1/transactions/renotify/' . $id,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'auth_bearer' => $token,
                    'json' => []
                ]);
            $data = json_decode($response->getContent(), true);
            $this->logger->info($response->getContent());
            return true;

        } catch (\Throwable $exception) {
            $this->logger->info($id);
            $this->logger->error($exception->getMessage());
            return 'error';
        }
    }

    public function saveToCollationIssues(array $issues): bool
    {
        // если есть команда, то перезапись иначе создаем новую
        $commandId = $this->entityManager->getRepository(CollationIssues::class)->findOneBy(['providerId' => $issues['provider_id']]);

//        dd($commandId);
        if ($commandId){
            if ($commandId->getType() === 'diff_base' && $commandId->getStatus() === 'charged'){
                return true;
            }
            // перезаписываем данные
            $this->deleteCommandIssues($commandId->getId());
        }

        $date = new DateTime('now', new DateTimeZone("+3"));

        $collation = new CollationIssues();

        $collation->setCommandId($issues['command_id']);
        $collation->setType($issues['type']);
        $collation->setDescription($issues['description']);
        $collation->setStatus($issues['status']);
        $collation->setCreatedAt($date);
        $collation->setProcessingId($issues['processing_id']);
        $collation->setProviderId($issues['provider_id']);

        $dt = new DateTime($issues['started'], new DateTimeZone("+3"));
        $collation->setStarted(DateTime::createFromFormat('Y-m-d H:i:s.u', $dt->format('Y-m-d H:i:s.u')));

        $this->entityManager->persist($collation);
        $this->entityManager->flush();

        $this->logger->info(sprintf('started = %s', $collation->getStarted()->format('Y-m-d H:i:s.u')));

        return true;
    }

    public function saveToCollationIssuesSQL(array $issues): bool
    {
        $conn = $this->entityManager->getConnection();

        $existing = $this->entityManager->getRepository(CollationIssues::class)
            ->findOneBy(['providerId' => $issues['provider_id']]);

        if ($existing) {
            $this->deleteCommandIssues($existing->getId());
        }

        $createdAt = (new \DateTime('now', new \DateTimeZone('+3')))->format('Y-m-d H:i:s.u');

        $dt = new \DateTime($issues['started'], new \DateTimeZone('+3'));
        $started = $dt->format('Y-m-d H:i:s.u');

        $conn->executeStatement('
        INSERT INTO reconciliation.collation_issues 
            (command_id, type, description, status, created_at, processing_id, provider_id, started)
        VALUES 
            (:command_id, :type, :description, :status, :created_at, :processing_id, :provider_id, :started)
    ', [
            'command_id'    => $issues['command_id'],
            'type'          => $issues['type'],
            'description'   => $issues['description'],
            'status'        => $issues['status'],
            'created_at'    => $createdAt,
            'processing_id' => $issues['processing_id'],
            'provider_id'   => $issues['provider_id'],
            'started'       => $started,
        ], [
            'command_id'    => \PDO::PARAM_INT,
            'type'          => \PDO::PARAM_STR,
            'description'   => \PDO::PARAM_STR,
            'status'        => \PDO::PARAM_STR,
            'created_at'    => \PDO::PARAM_STR,
            'processing_id' => \PDO::PARAM_INT,
            'provider_id'   => \PDO::PARAM_INT,
            'started'       => \PDO::PARAM_STR,
        ]);

        // Логируем дату, чтобы убедиться в микросекундах
//        $this->logger->info(sprintf('started = %s', $started));

        return true;
    }


    public function deleteCommandIssues( int $id ): array
    {
        $sql1 = /** @lang PostgreSQL */
            "
        delete from reconciliation.collation_issues where id = {$id};
        ";

        try {
            $stmt = $this->entityManager->getConnection()->prepare($sql1);
            $stmt->executeQuery();
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return [];
    }

    public function getCheckProcessingBase(): string
    {
        $sql = /** @lang PostgreSQL */ "
            select version();
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        $res = $statement->fetchAll();
        if (array_key_exists('version', $res[0])) {
            return 'ok';
        } else {
            return 'error';
        }
    }

    public function getCheckBase(): string
    {
        $connection = $this->entityManagerMain->getConnection();
        $sql = /** @lang PostgreSQL */
            "
        select version();
             ";

        $res = $connection->executeQuery($sql)->fetchAllAssociative();

//        dd($res);
        if (array_key_exists('version', $res[0])) {
            return 'ok';
        } else {
            return 'error';
        }
    }

    public function getInfo($date): array
    {
        $sql = /** @lang PostgreSQL */ "
            select status, count(id) from txs
            where 
                started >= date ('$date') at time zone 'utc' at time zone 'UTC-3' and
                started < date ('$date') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
              
            group by 1
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return [];
        }
    }

    public function getCharged($date): array
    {
        $sql = /** @lang PostgreSQL */ "
            select distinct provider, count(id) from txs
            where 
                started >= date ('$date') at time zone 'utc' at time zone 'UTC-3' and
                started < date ('$date') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
                and lower(status) in ('charged', 'refunded')
            group by 1;
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getChargedWithProvider($dateRequest, $provider, $utc): array
    {
        $sql = /** @lang PostgreSQL */ "
            select distinct provider, count(id) from txs
            where 
                started >= date ('$dateRequest') at time zone 'utc' at time zone '$utc' and
                started < date ('$dateRequest') at time zone 'utc' at time zone '$utc' + interval '1 day'
                and lower(status) in ('charged', 'refunded')
                and provider = '$provider'
            group by 1;
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $statement->execute();
        $arr = $statement->fetch(\PDO::FETCH_ASSOC);
        if (is_array($arr)) {
            return $arr;
        } else {
            return ["provider" => $provider, "count" => 0];
        }
    }

    public function getChargedForProvider($provider, $date, $utc = 'UTC-3'): int
    {
        $sql = /** @lang PostgreSQL */ "
            select count(id) from txs
            where 
                started >= date ('$date') at time zone 'utc' at time zone '$utc' and
                started < date ('$date') at time zone 'utc' at time zone '$utc' + interval '1 day'
                and lower(status) in ('charged','refunded')
                and provider = '$provider'
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchColumn();
        } else {
            return 0;
        }
    }

    //Balances
    public function createBalanceProcess($activeMerch, $commandDate): array
    {
//        dd($activeMerch);
        $this->logger->info(sprintf('[Creating...] : %s', $commandDate));
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));
            $d = new DateTime($commandDate,  new DateTimeZone("+3"));

            $process = new BalanceProcess();
            $process->setBalanceConfigId($this->balanceConfigRepository->find($activeMerch->getId()));
            $process->setTotalAmount($activeMerch->totalAmount);
            $process->setCounterInWas($activeMerch->counterInWas);
            $process->setCounterInBe($activeMerch->counterInBe);
            $process->setStatus('created');
            $process->setCreatedAt($date);
            $process->setOnDate($d);

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function createBalanceErrorProcess($activeMerch, $commandDate): array
    {
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));
            $d = new DateTime($commandDate,  new DateTimeZone("+3"));

            $process = new BalanceProcess();
            $process->setBalanceConfigId($this->balanceConfigRepository->find($activeMerch->getId()));
            $process->setTotalAmount(0);
            $process->setCounterInWas(0);
            $process->setCounterInBe(0);
            $process->setStatus('error');
            $process->setCreatedAt($date);
            $process->setOnDate($d);

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function deleteProcessForReCreate( int $id ): array
    {

        $sql = /** @lang PostgreSQL */
            "
        delete from reconciliation.balance_process where id = {$id};
        ";

        try {
            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $stmt->executeQuery();
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return [];
    }

    public function getRequestApiNoPage($method, $param): array|object
    {
        $token = $this->apiSession->updateStatusSession('POST');
        $response = $this->client->request($method, $this->processing_url . '/api/v1/' . trim($param),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'auth_bearer' => $token
            ]);

        if ($response->getStatusCode() !== 200) {
            return [];
        } else {
            $res = $response->toArray();

            if (isset($res['data'])) {
                $obj = json_decode($response->getContent());
                return $obj->data;
            } else {
                return json_decode($response->getContent());
            }
        }
    }


    public function getRequestApi(string $method, string $param, int $page = 1, array $allBalances = []): array|object
    {
        $token = $this->apiSession->updateStatusSession('POST');
        $urlParam = $param . (str_contains($param, '?') ? '&' : '?') . 'page=' . $page;

        $response = $this->client->request($method, $this->processing_url . '/api/v1/' . trim($urlParam), [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'auth_bearer' => $token
        ]);

        if ($response->getStatusCode() !== 200) {
            return $allBalances;
        }
        $res = $response->toArray();
        if (!isset($res['data'])) {
            return json_decode($response->getContent());
        }

        $allBalances = array_merge($allBalances, $res['data']);
        if (count($allBalances) < ($res['total'] ?? 0)) {
            return $this->getRequestApi($method, $param, $page + 1, $allBalances);
        }
        return json_decode(json_encode($allBalances));
    }

    public function getSumFutureCounterIn($partnerId, $start)
    {
        $sql = /** @lang PostgreSQL */ "
        select
        sum(amount),
        --sum(((REGEXP_REPLACE((fees->'sell')::text,'[\[\]]','','g')::json->'fee')::text)::int) as fee
        sum(merchant_wallet_amount) as fee
        from txs
        where lower(status) = 'charged' and type in ('payment') and
        started >= date ('$start') at time zone 'utc' at time zone 'UTC-3' and
        started < date ('$start') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
        and partner_id = {$partnerId};
        ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchObject();
        } else {
            return [];
        }
    }

    public function getSumFutureCounterInProvider($providerWalletId, $start)
    {
        $sql = /** @lang PostgreSQL */ "
        select
        sum(amount),
        sum(provider_wallet_amount) as fee
        from txs
        where lower(status) = 'charged' and type in ('payment') and
        started >= date ('$start') at time zone 'utc' at time zone 'UTC-3' and
        started < date ('$start') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
        and provider_wallet_id = {$providerWalletId};
        ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchObject();
        } else {
            return [];
        }
    }

    //Роллинг - 10% от суммы замораживает провайдер, но вскоре перечисляет её обратно нам
    public function getSumFutureCounterInProviderRolling($provider, $start)
    {
        $sql = /** @lang PostgreSQL */ "
        select
        sum(amount),
        round((sum(amount) / 10), 0) as fee
        from txs
        where lower(status) = 'charged' and type in ('payment') and
        started >= date ('$start') at time zone 'utc' at time zone 'UTC-3' and
        started < date ('$start') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
        and provider = '$provider';
        ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchObject();
        } else {
            return [];
        }
    }

    public function createErrorLogProcess($activeMerch, $error): array
    {
//        dd($activeMerch->getId());
        $desc = 'В процессе ' .$activeMerch->getId(). ' ошибка ' . $error;
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));

            $process = new BalanceErrorLog();
            $process->setProcessId($activeMerch->getId());
            $process->setDescription($desc);
            $process->setCreatedAt($date);

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function createErrorLogCommand($rowCommand, $error): array
    {
//        dd($rowCommand);
        $desc = 'В команде ' .$rowCommand->getId(). ' ошибка ' . $error;
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));

            $command = new RegistryErrorLog();
            $command->setCommandId($rowCommand->getId());
            $command->setDescription($desc);
            $command->setCreatedAt($date);
            $command->setStarted($date);
            $command->setStatus('error');
            $command->setType('');
            $command->setProviderId('');
            $command->setProcessingId('');

            $this->entityManager->persist($command);
            $this->entityManager->flush();

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function putRequestApi($method, $route, $data = false): array
    {
        $token = $this->apiSession->updateStatusSession('POST');

        $options = [
            'headers' => [
        'Content-Type' => 'application/json',
            ],
            'auth_bearer' => $token,
        ];

        if ($method === 'GET' && $data) {
            $options['query'] = $data;
        } else if ($data) {
            $options['json'] = $data;
        }

        $response = $this->client->request($method, $this->processing_url . '/api/v1/' . $route, $options);

        if ($response->getStatusCode() === 200) {
            return $response->toArray();
        }

        if ($response->getStatusCode() === 401) {
//            dd($response->getStatusCode());
//            $this->login();
//            return ['error' => $response->getStatusCode(), 'message' => $response->getInfo('debug')];
            return ['code' => $response->getStatusCode(), 'error' => $response->getContent(false)];
        }

        return ['code' => $response->getStatusCode(), 'error' => $response->getContent(false)];
    }

    public function createNewErrorProcess($activeMerch, $date, $error): array
    {
        try {
            foreach ($activeMerch as $id => $row) {
                $processId = $this->balanceProcessRepository->findCreatedProcess($row->getId(), $date);
                if (sizeof($processId) === 0) {
                    $this->createBalanceErrorProcess($row, $date->format('Y-m-d'));
                } else {
                    //Если процесс в этом статусе то не трогаем его.
                    if (!in_array($processId[0]->getStatus(), ['checking','checked','finishing','finished','error'], true)) {
                        $this->deleteProcessForReCreate($processId[0]->getId());
                        $this->createBalanceErrorProcess($row, $date->format('Y-m-d'));
                    }
                }
            }
            $process = $this->balanceProcessRepository->findAllErrorProcess($date);
            foreach ($process as $item){
                $this->createErrorLogProcess($item, $error);
            }

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    //email
    public function createNewErrorProcessEmail($activeMerch, $date, $error): array
    {
        try {
            foreach ($activeMerch as $id => $row) {
                $processId = $this->emailProcessRepository->findCreatedProcess($row->getId(), $date);
                if (sizeof($processId) === 0) {
                    $this->createEmailErrorProcess($row, $date->format('Y-m-d'));
                } else {
                    //Если процесс в этом статусе то не трогаем его.
                    if (!in_array($processId[0]->getStatus(), ['checking','checked','finishing','finished','error'], true)) {
                        $this->deleteProcessForReCreateEmail($processId[0]->getId());
                        $this->createEmailErrorProcess($row, $date->format('Y-m-d'));
                    }
                }
            }
            $process = $this->emailProcessRepository->findAllErrorProcess($date);
            foreach ($process as $item){
                $this->createErrorLogProcessEmail($item, $error);
            }

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function createEmailErrorProcess($activeMerch, $commandDate): array
    {
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));
            $d = new DateTime($commandDate,  new DateTimeZone("+3"));

            $process = new EmailProcess();
            $process->setEmailConfigId($this->emailConfigRepository->find($activeMerch->getId()));
            $process->setStatus('error');
            $process->setTotalAmount([0]);
            $process->setBalance([0]);
            $process->setCreatedAt($date);
            $process->setOnDate($d);

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function createEmailProcess($activeConfig, $commandDate): string
    {
        $this->logger->info(sprintf('[email_create][Creating...] за дату: %s,  партнер: %s', $commandDate, $activeConfig->getSlug()));

        try {
            $date = new DateTime('now', new DateTimeZone("+3"));
            $d = new DateTime($commandDate,  new DateTimeZone("+3"));

            $process = new EmailProcess();
            $process->setEmailConfigId($this->emailConfigRepository->find($activeConfig->getId()));
            $process->setTotalAmount([0]);
            $process->setBalance([0]);
            $process->setStatus('created');
            $process->setCreatedAt($date);
            $process->setOnDate($d);

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            $this->logger->info(sprintf('[email_create][Creating... ok]'));

            return "ok";

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function deleteProcessForReCreateEmail( int $id ): array
    {

        $sql = /** @lang PostgreSQL */
            "
        delete from reconciliation.email_process where id = {$id};
        ";

        try {
            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $stmt->executeQuery();
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        return [];
    }

    /**
     * @throws Exception
     */
    public function getStatusRegistryProcess(string $provider, string $onDate): array
    {
        $sql = /** @lang PostgreSQL */ "
        SELECT r.id, r.status
        FROM reconciliation.registry_command r
        LEFT JOIN reconciliation.registry_config c 
            ON c.id = r.registry_config_id
        WHERE c.provider = :provider
            AND r.on_date = :on_date
        ";

        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->bindValue('provider', $provider);
        $stmt->bindValue('on_date', $onDate);

        $result = $stmt->executeQuery();

        $data = $result->fetchAllAssociative();
        return array_column($data, 'status', 'id');
    }



    public function getDataForMerchantRegistryProvider($date, $utc, $terminalsId, $type, $status): array
    {
        $sql = /** @lang PostgreSQL */ "
            select t.id, t.txid, t.amount,
                t.provider_amount as enrollment,
                t.status, t.description, t.type,
                t.started::timestamp at time zone 'utc' at time zone '$utc' as started, 
                p.login as name
            from txs t join partners p on t.partner_id = p.id 
            where lower(status) = '$status' and type in ('$type') and
                t.started >= date ('$date') at time zone 'utc' at time zone '$utc' and
                t.started < date ('$date') at time zone 'utc' at time zone '$utc' + interval '1 day'
            and current_terminal in ($terminalsId);
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDataForMerchantRegistryMerchant($date, $utc, $terminalsId, $type, $status): array
    {
        $sql = /** @lang PostgreSQL */ "
            select t.id, t.txid, t.amount,
                t.merchant_amount as enrollment,
                t.status, t.description, t.type,
                t.started::timestamp at time zone 'utc' at time zone '$utc' as started, 
                p.login as name
            from txs t join partners p on t.partner_id = p.id 
            where lower(status) = '$status' and type in ('$type') and
                t.started >= date ('$date') at time zone 'utc' at time zone '$utc' and
                t.started < date ('$date') at time zone 'utc' at time zone '$utc' + interval '1 day'
            and current_terminal in ($terminalsId);
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDataForMerchantRegistryPercent($date, $utc, $terminalsId, $type, $status): array
    {
        $sql = /** @lang PostgreSQL */ "
            select t.id, t.outertxid, t.amount,
                t.merchant_amount as fee,
                t.status, t.description, t.type,
                t.started::timestamp(0) at time zone 'utc' at time zone '$utc' as started,
                regexp_replace(
                    trim((t.pd->>'sender')::jsonb->>'name'),
                    '^(\S)\S*',
                    '\\1'
                ) as sender
            from txs t join partners p on t.partner_id = p.id 
            where lower(status) = '$status' and type in ('$type') and
                t.started >= date ('$date') at time zone 'utc' at time zone '$utc' and
                t.started < date ('$date') at time zone 'utc' at time zone '$utc' + interval '1 day'
            and current_terminal in ($terminalsId);
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthDataForMerchantRegistryPercent($start, $end, $terminalsId, $status): array
    {
        $sql = /** @lang PostgreSQL */ "
            select t.id, t.outertxid, t.amount,
                t.merchant_amount as fee,
                t.status, t.description, t.type,
                t.started::timestamp(0) at time zone 'utc' at time zone 'UTC-3' as started,
                regexp_replace(
                    trim((t.pd->>'sender')::jsonb->>'name'),
                    '^(\S)\S*',
                    '\\1'
                ) as sender
            from txs t join partners p on t.partner_id = p.id 
            where lower(status) = '$status' and type in ('payment') and
                t.started >= date ('$start') at time zone 'utc' at time zone 'UTC-3' and
                t.started < date ('$end') at time zone 'utc' at time zone 'UTC-3'
            and current_terminal in ($terminalsId);
            ";

        $conn = $this->singleConnection->getConnection($this->app);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function createErrorLogProcessEmail($activeMerch, $error): array
    {
//        dd($activeMerch->getId());
        $desc = 'В процессе ' .$activeMerch->getId(). ' ошибка ' . $error;
        try {
            $date = new DateTime('now', new DateTimeZone("+3"));

            $process = new EmailErrorLog();
            $process->setProcessId($activeMerch->getId());
            $process->setDescription($desc);
            $process->setCreatedAt($date);

            $this->entityManager->persist($process);
            $this->entityManager->flush();

            return [];

        } catch (\Exception $e) {
            echo($e->getMessage());
            return [];
        }
    }

    public function validateEmailArray(array $arr): string|null
    {
        if ( sizeof($arr) > 0 ) {
            $val = [];
            foreach ($arr as $email) {
                if ($this->isValidEmail($email)) {
                    array_push($val, $email);
                } else {
                    return null;
                }
            }
            return count($val) ? implode(", ", $val) : null;
        } else {
            return null;
        }
    }

    // Функция для проверки электронной почты
    public function isValidEmail($email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // Акт
    public function getActData($data, $partnerId)
    {

        if (!empty($data['month'])) {
            $data['start'] = $data['month']->format("Y-m-d") . ' 00:00:00';
        }

        if (!empty($data['month'])) {
            $end = new DateTime($data['month']->format('Y-m-d'), new DateTimeZone("+3"));
            $end->modify('+1 month');
            $data['end'] = $end->format("Y-m-d") . ' 00:00:00';
        }

        if ($data['ids'] !== '0' ) {
            $integerIDs = array_map('intval', explode(',', $data['ids']));
            $ids = (implode(', ', $integerIDs));
            $partnerId = $partnerId . ', '.$ids;
        }

        $type = $data['type'];
        $start = $data['start'];
        $end = $data['end'];

        $sql = /** @lang PostgreSQL */ "
        select  sum(t.amount),
                sum((REGEXP_REPLACE((t.fees->'sell')::text,'[\[\]]','','g')::json->'fee')::text::int) as fee
            from txs t join partners p on t.partner_id = p.id
            where 
                started >= date ('$start') at time zone 'utc' at time zone 'UTC-3' and
                started < date ('$end') at time zone 'utc' at time zone 'UTC-3' and
                lower(status) = 'charged' and t.type in ('$type') and env = 'prod' and
                t.partner_id in ($partnerId);
        ";

        $conn = $this->singleConnection->getConnection($this->app);
        $statement = $conn->prepare($sql);
        $result = $statement->execute();

        if ($result) {
            return $statement->fetchObject();
        } else {
            return [];
        }
    }
}
