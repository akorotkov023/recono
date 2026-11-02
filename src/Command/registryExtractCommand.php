<?php

namespace App\Command;

use App\Entity\RegistryCommand;
use App\Helper\BaseHelper;
use App\Repository\RegistryConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class registryExtractCommand extends Command
{
    public function __construct(
        private BaseHelper $helper,
        private LoggerInterface $logger,
        protected string $reg_temp,
        private EntityManagerInterface $entityManager,
        private RegistryConfigRepository $registryConfigRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('registry_extract')
            ->setDescription('Template: "php bin/console registry_extract provider"')
            ->addArgument('provider', InputArgument::REQUIRED, 'Input provider')
            ->addOption('form', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $provider = $input->getArgument('provider');
//        dd($provider);
        $this->logger->info(sprintf("[$commandName] Начинаем смотреть файлы " . $provider));

        $config = $this->registryConfigRepository->findAllActiveProvider($provider);
//        dd($config);

        if (count($config) <> 0) {
            foreach ($config as $item) {
//              dd($item->getSlug());
                //находим реестр в папке
                $slug = $item->getSlug();
                $files = glob( $this->reg_temp . $slug ."*.csv" );
                $cnt = count($files);
                $this->logger->info("[$commandName] Нашли $cnt файл в temp: $slug");
                foreach ($files as $id => $row) {
                    $fileName = mb_substr($row, mb_strripos($row, "/", 0) + 1);
                    $this->logger->info("[$commandName] $fileName");
                    $commandId = $this->entityManager->getRepository(RegistryCommand::class)->findOneBy(['description' => $fileName]);
//                dd($commandId);
                    if ($commandId) {
                        $this->logger->info(sprintf("[$commandName] Сохраняем новый реестр из temp: %s", $fileName));
                        $this->helper->saveCommandAndTransaction($this->reg_temp . $fileName, $item, $commandId);
                    }
                }
            }

            return Command::SUCCESS;
        }
        $this->logger->warning(sprintf("[$commandName] Нет активных конфигов для: %s", $provider));

        return Command::SUCCESS;
    }
}
