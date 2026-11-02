<?php


namespace App\MessageHandler;


use App\Message\ExtractedRegistryMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class ExtractedRegistryMessageHandler implements MessageHandlerInterface
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @throws ProcessStartFailedException
     * @throws LogicException
     * @throws RuntimeException
     */
    public function __invoke(ExtractedRegistryMessage $message)
    {

        $provider = $message->getProvider();

//        $process = new Process(['/usr/local/bin/php', '../bin/console', 'registry_extract', $provider]);
//        $process->start();
//        $message = [];

//        foreach ($process as $type => $data) {
//            if ($process::OUT === $type) {
//                $message[] = $data;
//            } else {
//                $message[] = $data;
//            }
//        }

        $this->logger->info('[ExtractedRegistryMessage] Start');

        return Command::SUCCESS;
    }
}
