<?php

namespace App\Service\Imap;

/**
 * Минимальный IMAP-клиент поверх SSL-сокета (без ext-imap).
 *
 * Реализует только тот набор команд, который нужен для выгрузки реестров:
 * LOGIN, SELECT, SEARCH, FETCH (UID / RFC822), UID COPY, UID STORE +FLAGS, EXPUNGE, LOGOUT.
 */
class ImapClient
{
    /** @var resource|null */
    private $stream = null;

    private int $tag = 0;

    public function __construct(
        private string $host,
        private int $port = 993,
        private int $timeout = 60,
        private bool $verifyPeer = false,
        private bool $ssl = true,
    ) {
    }

    public function connect(): void
    {
        $errno = 0;
        $errstr = '';

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $this->verifyPeer,
                'verify_peer_name' => $this->verifyPeer,
            ],
        ]);

        $stream = @stream_socket_client(
            ($this->ssl ? 'ssl' : 'tcp') . "://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($stream === false) {
            throw new \RuntimeException(sprintf('IMAP: не удалось подключиться к %s:%d (%s: %s)', $this->host, $this->port, $errno, $errstr));
        }

        stream_set_timeout($stream, $this->timeout);
        $this->stream = $stream;

        // Приветствие сервера: * OK ...
        $this->readLine();
    }

    public function login(string $login, string $password): void
    {
        $status = $this->command('LOGIN ' . $this->quote($login) . ' ' . $this->quote($password));

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: ошибка входа: %s', $status->line));
        }
    }

    public function select(string $folder): int
    {
        $status = $this->command('SELECT ' . $this->quote($folder));

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: не удалось открыть папку %s: %s', $folder, $status->line));
        }

        // Количество писем берём из untagged-ответа "* N EXISTS".
        foreach ($status->lines as $line) {
            if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    /**
     * @return int[] номера писем (sequence numbers), удовлетворяющие критерию
     */
    public function search(string $criteria = 'ALL'): array
    {
        $status = $this->command('SEARCH ' . $criteria);

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: ошибка поиска писем: %s', $status->line));
        }

        $ids = [];
        foreach ($status->lines as $line) {
            if (preg_match('/^\*\s+SEARCH\s+(.*)$/i', $line, $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $num) {
                    if ($num !== '' && ctype_digit($num)) {
                        $ids[] = (int) $num;
                    }
                }
            }
        }

        return $ids;
    }

    public function fetchUid(int $sequence): int
    {
        $status = $this->command(sprintf('FETCH %d (UID)', $sequence));

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: не удалось получить UID письма %d: %s', $sequence, $status->line));
        }

        foreach ($status->lines as $line) {
            if (preg_match('/^\*\s+\d+\s+FETCH\s+\(.*\bUID\s+(\d+)/i', $line, $m)) {
                return (int) $m[1];
            }
        }

        throw new \RuntimeException(sprintf('IMAP: не найден UID для письма %d', $sequence));
    }

    public function fetchMessage(int $sequence): string
    {
        $status = $this->command(sprintf('FETCH %d (RFC822)', $sequence));

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: не удалось получить письмо %d: %s', $sequence, $status->line));
        }

        foreach ($status->lines as $line) {
            if (preg_match('/(?:RFC822|BODY\[\])\s+\{(\d+)\}(.*)$/is', $line, $m)) {
                $size = (int) $m[1];

                return substr($m[2], 0, $size);
            }
        }

        // Некоторые серверы возвращают тело не литералом, а в том же ответе (редко).
        throw new \RuntimeException(sprintf('IMAP: не удалось извлечь тело письма %d', $sequence));
    }

    public function uidCopy(int $uid, string $folder): void
    {
        $status = $this->command(sprintf('UID COPY %d %s', $uid, $this->quote($folder)));

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: не удалось скопировать письмо %d в %s: %s', $uid, $folder, $status->line));
        }
    }

    public function uidStoreDeleted(int $uid): void
    {
        $status = $this->command(sprintf('UID STORE %d +FLAGS (\Deleted)', $uid));

        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('IMAP: не удалось пометить письмо %d: %s', $uid, $status->line));
        }
    }

    public function expunge(): void
    {
        $this->command('EXPUNGE');
    }

    public function logout(): void
    {
        if ($this->stream !== null) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
                // игнорируем ошибки при закрытии
            }
        }
    }

    public function disconnect(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->stream !== null;
    }

    private function command(string $cmd): ImapStatus
    {
        if ($this->stream === null) {
            throw new \RuntimeException('IMAP: соединение не установлено');
        }

        $tag = 'A' . str_pad((string) ++$this->tag, 4, '0', STR_PAD_LEFT);

        if (fwrite($this->stream, $tag . ' ' . $cmd . "\r\n") === false) {
            throw new \RuntimeException('IMAP: ошибка записи в сокет');
        }

        $lines = [];

        while (true) {
            $line = $this->readLine();

            // untagged-ответы копим, tagged-ответ завершает команду
            if (str_starts_with($line, $tag . ' ')) {
                $status = new ImapStatus($tag, $line, $lines);
                break;
            }

            $lines[] = $line;
        }

        return $status;
    }

    private function readLine(): string
    {
        $line = fgets($this->stream);

        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            if (!empty($meta['timed_out'])) {
                throw new \RuntimeException('IMAP: таймаут чтения');
            }
            throw new \RuntimeException('IMAP: соединение закрыто сервером');
        }

        // Обработка литерала "{N}" в конце строки: сохраняем маркер "{N}",
        // читаем ровно N байт литерала, затем остаток строки.
        if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
            $size = (int) $m[1];
            $line = rtrim($line, "\r\n");
            $line .= $this->readBytes($size);
            $line .= $this->readLine();
        }

        return $line;
    }

    private function readBytes(int $length): string
    {
        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = fread($this->stream, $remaining);

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                if (!empty($meta['timed_out'])) {
                    throw new \RuntimeException('IMAP: таймаут чтения литерала');
                }
                throw new \RuntimeException('IMAP: соединение закрыто при чтении литерала');
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
