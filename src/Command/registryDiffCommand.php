<?php

namespace App\Command;

use App\Enum\RegistryCommandStatusEnum;
use App\Helper\BaseHelper;
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

#[AsCommand(
    name: 'registry_diff',
    description: 'Обнаружено расхождение смотрим так ли это: "php bin/console registry_diff date"',
)]
class registryDiffCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private BaseHelper $helper,
        private EntityManagerInterface $entityManager,
        private RegistryCommandRepository $registryCommandRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Input provider')
            ->addArgument('date', InputArgument::REQUIRED, 'Input date')
            ->addOption('process', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $provider = $input->getArgument('provider');
        $date = $input->getArgument('date');
        $process = $input->getOption('process');
        $processId = $this->registryCommandRepository->findAllCommandsByProviderAndDate($provider, $date);
        $utc = 'UTC-3';

        // Смотрим активные записи в настройках
        $this->logger->info(sprintf("[$commandName][start] Начинаем проверку { %s - %s }", $date, $provider));

        foreach ($processId as $item){
            $utc = $item->getRegistryConfigId()->getUtc();
        }
        if (isset($process)) {
            $this->logger->info(sprintf("[$commandName] process=( %s )", $process));
            //Запустить проверку на diff
            $flag = false;

            $base = $this->helper->getChargedForProvider($provider, $date, $utc);
            $reg = $this->registryCommandRepository->findSumTotalCountForProvider($provider, $date);

            if ($base !== $reg){
                $flag = true;
                //Если есть расхождение то меняем статус
                $command = $this->registryCommandRepository->find($process);
//                dd($command);
                if (!is_null($command)) {
                    $command->setStatus(RegistryCommandStatusEnum::EXTRACTED);
                    $this->entityManager->persist($command);
                    $this->entityManager->flush();
                    $this->logger->info(sprintf("[$commandName] Есть расхождение, меняем статус команды { process = %s }", $command->getId()));
                } else {
                    $this->logger->warning(sprintf("[$commandName] Нет команды за дату в статусе verified { %s }", $date));
                }
            }
            if (!$flag) {
                $this->logger->info(sprintf("[$commandName][end] Нет расхождения за дату { %s }", $date));
            }
        }
        return Command::SUCCESS;
    }
}
