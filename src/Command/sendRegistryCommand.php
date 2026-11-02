<?php

namespace App\Command;

use App\Repository\EmailConfigRepository;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'sendRegistry',
    description: 'Отправка реестра за дату php bin/console sendRegistry',
)]
class sendRegistryCommand extends Command
{

    public function __construct(
        protected string $in,
        protected string $out,
        private MailerInterface $mailer,
        private string $mailer_from,
        private EmailConfigRepository $emailConfigRepository,
        ?string $name = null
    )
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $end = new DateTime('NOW', new DateTimeZone("+3"));
        $endData = $end->format('Y-m-d H:i:s');
        $io->note('!Start! [' . $endData . ']');

        $activeConfigs = $this->emailConfigRepository->findAllActive();

        if (empty($activeConfigs)) {
            $io->warning('Нет активных email_config для отправки реестров');
            return Command::SUCCESS;
        }

        $filePath = $this->in;
        $files = glob( $filePath ."*.csv" );

        if (!empty($files)) {
            foreach ($files as $id => $row) {
                $fileName = mb_substr($row, mb_strripos($row, "/", 0) + 1);
                echo $fileName . PHP_EOL;

                foreach ($activeConfigs as $config) {
                    $to = array_values(array_filter($config->getEmailTo()));
                    $cc = array_values(array_filter($config->getEmailCc()));

                    if (empty($to)) {
                        continue;
                    }

                    $email = (new Email())
                        ->from($this->mailer_from)
                        ->to(...$to)
                        ->subject('Реестр за дату ' . $end->format('Y-m-d'))
                        ->html('')
                        ->attach(fopen($filePath . $fileName, 'r'), $fileName);

                    if (!empty($cc)) {
                        $email->cc(...$cc);
                    }

                    $this->mailer->send($email);
                }

                rename($filePath . $fileName, $this->out . $fileName);
            }
        } else {
            echo 'no files' . PHP_EOL;
        }

        $io->success('!Finish! {' . $endData . '}');
        return Command::SUCCESS;
    }
}
