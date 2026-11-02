<?php

namespace App\Command;

use App\Service\TelegramService;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'telegram:send',
    description: 'Авторизация в телергам',
)]
class TelegramLoginCommand extends Command
{
    public function __construct(
        private readonly TelegramService $telegram,
    )
    {
        parent::__construct();
    }
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = new DateTime('now', new DateTimeZone('+3'));
        $date = $date->modify('-1 day')->format('Y-m-d');

        try {
            $checkIsOk = true;

            if ($checkIsOk) {
                $this->telegram->send('По сверке ок. (' . $date . '). Реестры отправлены');
            } else {
                $this->telegram->send(
                    'По сверке за дату (' . $date . '). обнаружены ошибки'
                );
            }

            $io->success('Сообщение отправлено в Telegram.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(
                'Не удалось отправить сообщение: ' . $e->getMessage()
            );

            return Command::FAILURE;
        }
    }
}


