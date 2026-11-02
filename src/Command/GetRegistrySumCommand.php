<?php


namespace App\Command;


use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GetRegistrySumCommand extends Command
{

    protected ObjectManager $entityManagerProcessing;
    protected ObjectManager $entityManagerMain;

    public function __construct(ManagerRegistry $doctrine)
    {
//        $this->entityManagerProcessing = $doctrine->getManager('processing');
        $this->entityManagerMain = $doctrine->getManager();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('getRegistrySum');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $io = new SymfonyStyle($input, $output);
        $io->note('Начинаем смотреть суммы');

        $connection = $this->entityManagerMain->getConnection();
        $sql = /** @lang PostgreSQL */
            "
        select t.command_id, 
               c.description as name,  
               (sum(t.amount/100.0)::numeric(10,2)), 
               count(t.id) 
        from reconciliation.provider_registry_transactions t 
        join reconciliation.provider_registry_command c on t.command_id = c.id
        group by 1,2 limit 2
             ";

        $result = $connection->executeQuery($sql)->fetchAllAssociative();

        $io->info(sprintf('Сумма  = ' . $result[0]['sum'] . '  Количество = ' . $result[0]['count']) );

        return Command::SUCCESS;
    }
}
