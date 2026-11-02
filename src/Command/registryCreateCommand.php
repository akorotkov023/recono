<?php


namespace App\Command;


use App\Entity\RegistryCommand;
use App\Helper\BaseHelper;
use App\Repository\RegistryConfigRepository;
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
    name: 'registry_create',
    description: 'php bin/console registry_create 2024-05-07 --form=0',
)]
class registryCreateCommand extends Command
{

    public function __construct(
        private LoggerInterface $logger,
        private BaseHelper $helper,
        private EntityManagerInterface $entityManager,
        private RegistryConfigRepository $registryConfigRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('date', InputArgument::REQUIRED, 'Input date')
            ->addOption('form', null,InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $this->getName();
        $io = new SymfonyStyle($input, $output);
        $d = $input->getArgument('date');
        $date = new DateTime($d);
        $on_date = $date->format('Y-m-d');

        $this->logger->info(sprintf("[$commandName] Создаем команды за дату %s", $on_date));

        $configs = $this->registryConfigRepository->findAllActiveObject();
//        dd($configs);
        $commands = $this->entityManager->getRepository(RegistryCommand::class)->findBy(['onDate' => $date]);

        //TODO переделать этот срам
        if (count($configs) <> 0) {
            foreach ($configs as $config) {
//                dd($config->getSlug());
                if (count($commands) <> 0) {
                    $desc = [];
                    foreach($commands as $key => $command){
                        $desc[] = mb_substr($command->getDescription(), 0, -14);
                    }
                    if (!in_array($config->getSlug(), $desc, true)) {
                        $io->note(' -> ' . $config->getSlug() . $on_date);
                        $this->helper->createNewRegistryCommand($config, $on_date);
                    }
                } else {
                    $io->note(' -> ' . $config->getSlug().$on_date);
                    $this->helper->createNewRegistryCommand($config, $on_date);
                    $this->logger->info(sprintf("[$commandName] Нет комманд которые можно создать"));
                }
            }
        } else {
            $this->logger->info(sprintf("[$commandName] Нет активных параметров в Сверка -> Настройки"));
            $this->logger->info(sprintf("[$commandName][end]"));
            return Command::SUCCESS;
        }
        return Command::SUCCESS;
    }

}
