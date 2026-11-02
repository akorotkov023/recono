<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'setStatus',
    description: 'Обновить статус транзакции Пример: php bin/console setStatus b731392a-fea7-4d62-a325-45d6b3c7456e',
)]
class SetStatusCommand extends Command
{
    public function __construct(
        #[Autowire('%env(PROCESSING_API_URL)%')]
        private string $processingUrl
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::REQUIRED, 'Input token')
            ->addArgument('id', InputArgument::REQUIRED, 'Input id')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = $input->getArgument('token');
        $id = $input->getArgument('id');
        $io = new SymfonyStyle($input, $output);
        $io->note('Start = ' . $id);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => rtrim($this->processingUrl, '/').'/api/v1/transactions/set_status/'.$id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
    "status": "Rejected",
    "error_description": "Статус - отклонено"
}',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '. $token,
                'Content-Type: application/json'
            ),
        ));
        $str = curl_exec($curl);
        curl_close($curl);

        $result = null;

        if (json_decode($str, true)){
            $res = json_decode($str, true);
            if (isset($res['status'])) {
                $result = $res['status'];
            }
            if (isset($res['errors'])) {
                $result = $res['errors'][0]['detail'];
            }
        }

        if ($result) {
            if ($result === 'rejected') {
                $io->success('Done. status = '. $result);
                return Command::SUCCESS;
            } else {
                $io->info('Error. '. $result);
                return Command::INVALID;
            }

        } else {
            $io->error('BIG ERROR. Что-то пошло не так!');
            $io->info($str);
            return Command::FAILURE;
        }
    }
}
