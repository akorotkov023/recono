<?php

namespace App\Service;

use App\Service\Imap\ImapClient;
use App\Service\Imap\MimeParser;
use Psr\Log\LoggerInterface;

/**
 * Получение реестров провайдера из почты и их преобразование в формат сверки.
 *
 * Имя выходного файла формируется из slug провайдера (registry_config.slug).
 * Обрабатываются все вложения писем с темой SUBJECT_MARKER.
 */
class RegistryMailService
{
    private const SUBJECT_MARKER = 'Реестр платежей от';

    private const HEADER = ['type', 'processing_id', 'provider_id', 'amount', 'fee', 'cardnumber', 'approve_code', 'transaction_date'];

    public function __construct(
        private LoggerInterface $logger,
        private MimeParser $mimeParser,
        private string $regTemp,
        private string $regProvider,
        private string $imapHost,
        private int $imapPort,
        private string $imapLogin,
        private string $imapPassword,
        private string $imapFolderPayments,
        private string $imapFolderSaved,
        private bool $imapSslVerify,
    ) {
    }

    /**
     * Забирает реестры из почтового ящика.
     *
     * @param string $slug префикс имени выходного файла (registry_config.slug)
     *
     * @return int количество обработанных вложений
     */
    public function fetchFromMail(string $slug): int
    {
        $imap = new ImapClient($this->imapHost, $this->imapPort, 60, $this->imapSslVerify);
        $imap->connect();
        $this->logger->info('[registry_get_mail] Подключились к IMAP');

        try {
            $imap->login($this->imapLogin, $this->imapPassword);
            $exists = $imap->select($this->imapFolderPayments);
            $this->logger->info(sprintf('[registry_get_mail] Писем в папке %s: %d', $this->imapFolderPayments, $exists));

            $ids = $imap->search('ALL');

            if (count($ids) === 0) {
                $this->logger->info('[registry_get_mail] Нет писем');

                return 0;
            }

            $uids = [];
            $cntFiles = 0;

            foreach ($ids as $num) {
                $uids[] = $imap->fetchUid($num);

                $raw = $imap->fetchMessage($num);
                $message = $this->mimeParser->parse($raw);

                if (!str_contains($message->subject, self::SUBJECT_MARKER)) {
                    continue;
                }

                foreach ($message->attachments as $attachment) {
                    $this->logger->info(sprintf('[registry_get_mail] Найден нужный файл: %s', $attachment->filename));
                    $this->processAttachment($attachment->filename, $attachment->content, $slug);
                    $cntFiles++;
                }
            }

            // Переносим все письма в папку saved
            foreach ($uids as $uid) {
                $imap->uidCopy($uid, $this->imapFolderSaved);
                $imap->uidStoreDeleted($uid);
            }
            $imap->expunge();

            $this->logger->info(sprintf('[registry_get_mail] Количество обработанных файлов = %d', $cntFiles));

            return $cntFiles;
        } finally {
            $imap->logout();
            $imap->disconnect();
        }
    }

    /**
     * Обрабатывает локальные файлы в REG_TEMP.
     *
     * @param string $slug префикс имени выходного файла (registry_config.slug)
     *
     * @return int количество обработанных файлов
     */
    public function processLocalTemp(string $slug): int
    {
        $cntFiles = 0;
        $files = glob($this->regTemp . '*.*');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $filename = basename($file);
            $content = file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $this->logger->info(sprintf('[registry_get_mail] Обрабатываем файл: %s', $filename));
            $this->processAttachment($filename, $content, $slug);
            @unlink($file);
            $cntFiles++;
        }

        $this->logger->info(sprintf('[registry_get_mail] Количество обработанных файлов = %d', $cntFiles));

        return $cntFiles;
    }

    /**
     * Преобразует один исходный файл и раскладывает результат по каталогам.
     */
    public function processAttachment(string $filename, string $content, string $slug): string
    {
        $date = $this->extractDateFromFilename($filename);

        if ($date === null) {
            throw new \RuntimeException(sprintf('Не удалось определить дату из имени файла: %s', $filename));
        }

        $newContent = $this->convertSource($content);
        $newName = sprintf('%s%s.csv', $slug, $date);

        $this->writeFile($this->regProvider . $filename, $content);
        $this->writeFile($this->regProvider . $newName, $newContent);
        $this->writeFile($this->regTemp . $newName, $newContent);

        return $newName;
    }

    /**
     * Определяет дату реестра из имени исходного файла (<d-m-Y>.csv).
     */
    public function extractDateFromFilename(string $filename): ?string
    {
        $base = basename($filename);
        $pos = strrpos($base, '_');

        if ($pos === false) {
            return null;
        }

        $dateStr = str_replace('.csv', '', substr($base, $pos + 1));
        $date = \DateTimeImmutable::createFromFormat('d-m-Y', $dateStr);

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Преобразует содержимое исходного реестра в формат сверки.
     */
    public function convertSource(string $sourceContent): string
    {
        $result = $this->encodeRow(self::HEADER);

        $source = new \SplTempFileObject();
        $source->fwrite($sourceContent);
        $source->rewind();
        $source->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $source->setCsvControl(';');

        $count = 0;

        foreach ($source as $row) {
            // пропускаем строку-шапку
            if ($count === 0) {
                $count++;

                continue;
            }

            if ($row === false || $row === [null]) {
                continue;
            }

            $amount = (int) str_replace('.', '', (string) ($row[3] ?? ''));
            $id = str_replace(' ', '', (string) ($row[8] ?? ''));

            $result .= $this->encodeRow([
                'payment',
                substr($id, 3),
                str_replace(' ', '', (string) ($row[0] ?? '')),
                $amount,
                '',
                str_replace(' ', '', (string) ($row[4] ?? '')),
                str_replace(' ', '', (string) ($row[9] ?? '')),
                $row[1] ?? '',
            ]);

            $count++;
        }

        return $result;
    }

    /**
     * Кодирует строку CSV в стиле python csv.writer (QUOTE_MINIMAL): кавычки
     * добавляются только если поле содержит разделитель, кавычку или перевод строки.
     */
    private function encodeRow(array $fields): string
    {
        foreach ($fields as &$field) {
            $field = (string) $field;

            if (strpbrk($field, ";\r\n\"") !== false) {
                $field = '"' . str_replace('"', '""', $field) . '"';
            }
        }
        unset($field);

        return implode(';', $fields) . "\r\n";
    }

    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Не удалось создать каталог: %s', $dir));
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException(sprintf('Не удалось записать файл: %s', $path));
        }
    }
}
