<?php

namespace App\Service\Imap;

/**
 * Результат выполнения IMAP-команды: tagged-статус и накопленные untagged-строки.
 */
class ImapStatus
{
    /**
     * @param string   $tag   тег команды
     * @param string   $line  строка tagged-статуса (например "A0001 OK ...")
     * @param string[] $lines untagged-ответы сервера
     */
    public function __construct(
        public string $tag,
        public string $line,
        public array $lines,
    ) {
    }

    public function isOk(): bool
    {
        return preg_match('/\bOK\b/i', $this->line) === 1;
    }
}
