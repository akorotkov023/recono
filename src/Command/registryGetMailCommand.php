<?php

namespace App\Command;

use App\Repository\RegistryConfigRepository;
use App\Service\RegistryMailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class registryGetMailCommand extends Command
{
    public function __construct(
        private LoggerInterface $parserLogger,
        private RegistryMailService $registryMailService,
        private RegistryConfigRepository $registryConfigRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('registry_get_mail')
            ->setDescription('Проверка почты. Template: php bin/console registry_get_mail provider --date=2025-10-31')
            ->addArgument('provider', InputArgument::REQUIRED, 'Input provider')
            ->addOption('form', null, InputOption::VALUE_REQUIRED)
            ->addOption('date', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->parserLogger->info('Start command registry_get_mail');
        $provider = $input->getArgument('provider');

        $io = new SymfonyStyle($input, $output);
        $this->parserLogger->info('Provider = ' . $provider);
        //TODO проверять только ту почту которая включена в настройках или при выборе

        $isTemp = str_ends_with($provider, '_temp');
        $providerName = $isTemp ? substr($provider, 0, -5) : $provider;

        try {
            $configs = $this->registryConfigRepository->findAllActiveProvider($providerName);

            if (count($configs) === 0) {
                $io->note('Нет такого провайдера!');

                return Command::SUCCESS;
            }

            foreach ($configs as $config) {
                $slug = $config->getSlug();
                if ($isTemp) {
                    $this->registryMailService->processLocalTemp($slug);
                } else {
                    $this->registryMailService->fetchFromMail($slug);
                }
            }
        } catch (\Exception $e) {
            $io->note($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
