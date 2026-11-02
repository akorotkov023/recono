<?php

namespace App\Service;

use danog\MadelineProto\Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $botToken,
        private readonly string $chatId,
    ) {
    }

    /**
     * @throws Exception
     */
    public function send(string $message): void
    {
        $response = $this->httpClient->request(
            'POST',
            sprintf(
                'https://api.telegram.org/bot%s/sendMessage',
                $this->botToken
            ),
            [
                'json' => [
                    'chat_id' => $this->chatId,
                    'text' => $message,
                ],
            ]
        );
        $data = $response->toArray();

        if (($data['ok'] ?? false) !== true) {
            throw new \RuntimeException(
                $data['description'] ?? 'Ошибка отправки сообщения в Telegram'
            );
        }

    }

}
