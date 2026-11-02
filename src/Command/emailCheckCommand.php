<?php

namespace App\Command;

use App\Helper\BaseHelper;
use App\Enum\RegistryTypeEnum;
use App\Repository\EmailProcessRepository;
use App\Repository\RegistryCommandRepository;
use App\Service\RegistryFileGenerator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Exception\CurrencyMismatchException;
use Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'email_check',
    description: 'php bin/console email_check 2024-05-07 --process=0 --id=0',
)]
class emailCheckCommand extends Command
{
    public function __construct(
        private EmailProcessRepository $emailProcessRepository,
        private RegistryFileGenerator $fileGenerator,

        private BaseHelper $helper,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private RegistryCommandRepository $providerRegistryCommandRepository,
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

    /**
     * @throws InvalidArgumentException
     * @throws CurrencyMismatchException
     * @throws \Money\Exception\InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');
        $cnt = [];

        $id = $input->getOption('id');
        $process = $input->getOption('process');

        $this->logger->info(sprintf("[$commandName] start"));

        //Собираем все процессы в статусе created за дату и конфигу если он есть в запросе
        $processId = $this->emailProcessRepository->findAllCreatedProcess($on_date, $id);
        $fee_31 = new Money(31, new Currency('RUB')); // 31 копейка
        $fee_49 = new Money(49, new Currency('RUB')); // 49 копеек

        if (isset($process)) {
            //запустить команду по process
            if ($process > 0) {
                $this->logger->info("[$commandName] Запуск команды по опции process=( $process )");
                $processId = $this->emailProcessRepository->findOneCreatedProcess($process);
            }
        }

//        dd($processId);

        if (sizeof($processId) !== 0) {
            $this->logger->info(sprintf("[$commandName] Собрать суммы для реестра"));
            foreach ($processId as $item) {
                $utc = $item->getEmailConfigId()->getUtc();
                $terminalsId = implode(', ', $item->getEmailConfigId()->getTerminal());
                $slug = $item->getEmailConfigId()->getSlug();
                $format = $item->getEmailConfigId()->getFormat();
                $types = $item->getEmailConfigId()->getType();
                $registryType = $item->getEmailConfigId()->getRegistryType();
                $isRefund = $item->getEmailConfigId()->getIsRefundChecked();
                $balances = $item->getBalance();
                $fileRegNames = [];
                $sumOfRegs = [];
//                $partnerId = $item->getEmailConfigId()->getPartnerId();
                $cnt = [
                    'all' => 0,
                    'count_250' => 0,
                    'count_350' => 0,
                    'count_other' => 0
                ];

                $item->setStatus('checking');
                $this->entityManager->persist($item);
                $this->entityManager->flush();

                if ($registryType === RegistryTypeEnum::PERCENT) {
                    $endData = new DateTime($on_date);
                    $endData = $endData->format('Y.m.d');

                    // Реестр по умолчанию
                    foreach ($types as $type) {
                        if ($type !== '') {
                            $transactions = $this->helper->getDataForMerchantRegistryPercent($on_date, $utc, $terminalsId, $type, 'charged');

                            $cnt['all'] = count($transactions) ?? 0;

                            $refundedList = $this->helper->getDataForMerchantRegistryPercent($on_date, $utc, $terminalsId, $type, 'refunded');
//                            dd($refundedList);

                            if (sizeof($transactions) !== 0) {
                                //считаем тотал
                                $sum_amount = new Money(0, new Currency('RUB'));
                                $sum_fee = new Money(0, new Currency('RUB'));

                                foreach ($transactions as $key => $val) {
                                    $amount = new Money((int)$val['amount'], new Currency('RUB'));

                                    // считаем 0.4% от суммы
                                    $fee = $amount->multiply('0.004', Money::ROUND_HALF_UP);

                                    // Добавляем значения в общую сумму
                                    $sum_amount = $sum_amount->add($amount);
                                    $sum_fee = $sum_fee->add($fee);

                                    $transactions[$key]['amount'] = number_format($amount->getAmount() / 100, 2, '.', '');
                                    $transactions[$key]['fee'] = number_format($fee->getAmount() / 100, 2, '.', '');
                                }

                                array_push($sumOfRegs, number_format($sum_amount->getAmount() / 100, 2, '.', ''));
                                array_push($sumOfRegs, number_format($sum_fee->getAmount()  / 100, 2, '.', ''));

                                //считаем тотал refund
                                $sum_amount_refund = new Money(0, new Currency('RUB'));
                                $sum_fee_refund = new Money(0, new Currency('RUB'));

                                foreach ($refundedList as $key => $val) {
                                    $amount = new Money((int)$val['amount'], new Currency('RUB'));

                                    // считаем 0.4% от суммы
                                    $fee = $amount->multiply('0.004', Money::ROUND_HALF_UP);

                                    // Добавляем значения в общую сумму
                                    $sum_amount_refund = $sum_amount_refund->add($amount);
                                    $sum_fee_refund = $sum_fee_refund->add($fee);

                                    $refundedList[$key]['amount'] = number_format($amount->getAmount() / 100, 2, '.', '');
                                    $refundedList[$key]['fee'] = number_format($fee->getAmount() / 100, 2, '.', '');
                                }

                                array_push($sumOfRegs, number_format($sum_amount_refund->getAmount() / 100, 2, '.', ''));
                                array_push($sumOfRegs, number_format($sum_fee_refund->getAmount()  / 100, 2, '.', ''));

                                //формируем реестр
                                $file = $this->fileGenerator->exportToFileDefault($format, $type, $slug, $transactions, $endData);
                                $file2 = $this->fileGenerator->exportToFileDefault($format, $type, 'refunded_'.$slug, $refundedList, $endData);

                                if (isset($file2)) {
                                    array_push($fileRegNames, $file2);
                                }
                                if (isset($file)) {
                                    array_push($fileRegNames, $file);
                                } else {
                                    $error = 'Реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcess($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            } else {
                                //Если нет данных то формируем пустой реестр
                                array_push($sumOfRegs, 0);

                                $file3 = $this->fileGenerator->exportToFileBR($format, $type, $slug, [], $endData);
                                if (isset($file3)) {
                                    array_push($fileRegNames, $file3);
                                } else {
                                    $error = 'Пустой реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcessEmail($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");
                                    return Command::INVALID;
                                }
                            }
                        }
                    }
                }  elseif ($registryType === RegistryTypeEnum::PROVIDER_AMOUNT && $isRefund) {
                    foreach ($types as $type) {
                        if ($type !== '') {

                            $transactions = $this->helper->getDataForMerchantRegistryProvider($on_date, $utc, $terminalsId, $type, 'charged');
                            $cnt['all'] = count($transactions) ?? 0;

                            $refundedList = $this->helper->getDataForMerchantRegistryProvider($on_date, $utc, $terminalsId, $type, 'refunded');

                            if (sizeof($transactions) !== 0) {

                                //считаем тотал
                                $sum_amount = new Money(0, new Currency('RUB'));
                                $sum_fee = new Money(0, new Currency('RUB'));

                                foreach ($transactions as $key => $val) {
                                    $amount = new Money((int)$val['amount'], new Currency('RUB'));
                                    $fee = new Money((int)$val['enrollment'], new Currency('RUB'));

                                    // Добавляем значения в общую сумму
                                    $sum_amount = $sum_amount->add($amount);
                                    $sum_fee = $sum_fee->add($fee);
                                }

                                array_push($sumOfRegs, number_format($sum_amount->getAmount() / 100, 2, '.', ''));
                                array_push($sumOfRegs, number_format($sum_fee->getAmount()  / 100, 2, '.', ''));

                                //формируем реестр
                                $file = $this->fileGenerator->exportToFile($format, $type, $slug, $transactions, $on_date);

                                if (isset($file)) {
                                    array_push($fileRegNames, $file);
                                } else {
                                    $error = 'Реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcess($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            } else {
                                //Если нет данных то формируем пустой реестр
                                array_push($sumOfRegs, 0);

                                $file = $this->fileGenerator->exportToFile($format, $type, $slug, [], $on_date);
                                if (isset($file)) {
                                    array_push($fileRegNames, $file);
                                } else {
                                    $error = 'Пустой реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcessEmail($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            }
                            if (sizeof($refundedList) !== 0) {

                                //считаем тотал
                                $sum_amount_refund = new Money(0, new Currency('RUB'));
                                $sum_fee_refund = new Money(0, new Currency('RUB'));

                                foreach ($refundedList as $key => $val) {
                                    $amount = new Money((int)$val['amount'], new Currency('RUB'));
                                    $fee = new Money((int)$val['enrollment'], new Currency('RUB'));

                                    // Добавляем значения в общую сумму
                                    $sum_amount_refund = $sum_amount_refund->add($amount);
                                    $sum_fee_refund = $sum_fee_refund->add($fee);
                                }

                                array_push($sumOfRegs, number_format($sum_amount_refund->getAmount() / 100, 2, '.', ''));
                                array_push($sumOfRegs, number_format($sum_fee_refund->getAmount()  / 100, 2, '.', ''));

                                //формируем реестр
                                $file2 = $this->fileGenerator->exportToFile($format, $type, 'refunded_'.$slug, $refundedList, $on_date);

                                if (isset($file2)) {
                                    array_push($fileRegNames, $file2);
                                } else {
                                    $error = 'Реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcess($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            } else {
                                //Если нет данных то формируем пустой реестр
                                array_push($sumOfRegs, 0);

                                $file2 = $this->fileGenerator->exportToFile($format, $type, 'refunded_'.$slug, [], $on_date);
                                if (isset($file2)) {
                                    array_push($fileRegNames, $file2);
                                } else {
                                    $error = 'Пустой реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcessEmail($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            }
                        }
                    }
                }  elseif ($registryType === RegistryTypeEnum::PROVIDER_AMOUNT) {
                    $endData = new DateTime($on_date);
                    $endData = $endData->format('Y.m.d');

                    foreach ($types as $type) {
                        if ($type !== '') {
                            $transactions = $this->helper->getDataForMerchantRegistryProvider($on_date, $utc, $terminalsId, $type, 'charged');

                            $cnt['all'] = count($transactions) ?? 0;

                            if (sizeof($transactions) !== 0) {
                                //считаем тотал
                                $sum_amount = new Money(0, new Currency('RUB'));
                                $sum_fee = new Money(0, new Currency('RUB'));

                                foreach ($transactions as $key => $val) {
                                    $amount = new Money((int)$val['amount'], new Currency('RUB'));
                                    $fee = new Money((int)$val['enrollment'], new Currency('RUB'));

                                    // Добавляем значения в общую сумму
                                    $sum_amount = $sum_amount->add($amount);
                                    $sum_fee = $sum_fee->add($fee);
                                }

                                array_push($sumOfRegs, number_format($sum_amount->getAmount() / 100, 2, '.', ''));
                                array_push($sumOfRegs, number_format($sum_fee->getAmount()  / 100, 2, '.', ''));

                                //формируем реестр
                                $file = $this->fileGenerator->exportToFile($format, $type, $slug, $transactions, $endData);

                                if (isset($file)) {
                                    array_push($fileRegNames, $file);
                                } else {
                                    $error = 'Реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcess($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            } else {
                                //Если нет данных то формируем пустой реестр
                                array_push($sumOfRegs, 0);

                                $file3 = $this->fileGenerator->exportToFile($format, $type, $slug, [], $endData);
                                if (isset($file3)) {
                                    array_push($fileRegNames, $file3);
                                } else {
                                    $error = 'Пустой реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcessEmail($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            }
                        }
                    }
                } else {
                    /* Реестр по умолчанию */
                    $endData = new DateTime($on_date);
                    $endData = $endData->format('Y.m.d');

                    foreach ($types as $type) {
                        if ($type !== '') {
                            $transactions = $this->helper->getDataForMerchantRegistryMerchant($on_date, $utc, $terminalsId, $type, 'charged');

                            $cnt['all'] = count($transactions) ?? 0;

                            if (sizeof($transactions) !== 0) {
                                //считаем тотал
                                $sum_amount = new Money(0, new Currency('RUB'));
                                $sum_fee = new Money(0, new Currency('RUB'));

                                foreach ($transactions as $key => $val) {
                                    $amount = new Money((int)$val['amount'], new Currency('RUB'));
                                    $fee = new Money((int)$val['enrollment'], new Currency('RUB'));

                                    // Добавляем значения в общую сумму
                                    $sum_amount = $sum_amount->add($amount);
                                    $sum_fee = $sum_fee->add($fee);
                                }

                                array_push($sumOfRegs, number_format($sum_amount->getAmount() / 100, 2, '.', ''));
                                array_push($sumOfRegs, number_format($sum_fee->getAmount()  / 100, 2, '.', ''));

                                //формируем реестр
                                $file = $this->fileGenerator->exportToFile($format, $type, $slug, $transactions, $endData);

                                if (isset($file)) {
                                    array_push($fileRegNames, $file);
                                } else {
                                    $error = 'Реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcess($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            } else {
                                //Если нет данных то формируем пустой реестр
                                array_push($sumOfRegs, 0);

                                $file3 = $this->fileGenerator->exportToFile($format, $type, $slug, [], $endData);
                                if (isset($file3)) {
                                    array_push($fileRegNames, $file3);
                                } else {
                                    $error = 'Пустой реестр не создан!';
                                    $io->warning($error);
                                    $this->helper->createErrorLogProcessEmail($item, $error);
                                    $item->setStatus('error');
                                    $this->entityManager->persist($item);
                                    $this->entityManager->flush();
                                    $this->logger->warning("[$commandName] $error");

                                    return Command::INVALID;
                                }
                            }
                        }
                    }
                }

                $item->setStatus('checked');
                $item->setRegs($fileRegNames);
                $item->setTotalAmount($sumOfRegs);
                $item->setCount($cnt);
                $this->entityManager->persist($item);
                $this->entityManager->flush();
                $this->logger->info("[$commandName] $slug - Завершил");

            }
            $this->logger->info("[$commandName] finish");
            return Command::SUCCESS;
        }

        $this->logger->warning("[$commandName] Нет процессов в статусе created за дату $on_date");
        return Command::SUCCESS;
    }

}
