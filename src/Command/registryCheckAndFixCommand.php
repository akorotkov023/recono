<?php

namespace App\Command;

use App\Enum\RegistryCommandStatusEnum;
use App\Helper\BaseHelper;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;

#[AsCommand(
    name: 'registry_check_fix',
    description: 'Проверка реестров за дату: "php bin/console registry_check provider date"',
)]
class registryCheckAndFixCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private BaseHelper $helper,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'Input provider')
            ->addArgument('date', InputArgument::REQUIRED, 'Input date');
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $provider = $input->getArgument('provider');
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');

        $this->logger->info("[$commandName] Начинаем проверку ");

//        $commands = $this->helper->getStatusRegistryProcess($provider, $on_date);

        $startTime = time();
        $timeout = 1 * 60; // 1 минуты

        while (true) {
            $time = time() - $startTime;
            $this->logger->info("[$commandName] Время: $time");
            $commands = $this->helper->getStatusRegistryProcess($provider, $on_date);

            $allVerified = true;

            foreach ($commands as $id => $status) {

                if (in_array($status, [RegistryCommandStatusEnum::EXTRACTED])) {
                    //TODO запустить registry_check
                    $this->logger->info("[$commandName] запустить registry_check $id");
                    $allVerified = false;
                    break;
                }
                if (in_array($status, [RegistryCommandStatusEnum::ISSUES])) {
                    //TODO запустить registry_fix
                    $this->logger->info("[$commandName] запустить registry_fix $id");
                    $allVerified = false;
                    break;
                }
                //TODO добавить проверку на new
            }

            if ($allVerified) {
                $this->logger->info("[$commandName] Все команды VERIFIED");

                return Command::SUCCESS;
            }

            if ($time >= $timeout) {
                $this->logger->warning(
                    "[$commandName] Таймаут 1 минуты. Остановка команды."
                );

                return Command::FAILURE;
            }

            sleep(15);
        }
    }
}
