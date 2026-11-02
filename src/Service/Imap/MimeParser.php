<?php

namespace App\Service\Imap;

/**
 * Разбор RFC822-писем (multipart/вложения, кодировки заголовков, base64/quoted-printable).
 *
 * Использует только встроенные функции PHP (iconv/mbstring), без внешних библиотек.
 */
class MimeParser
{
    public function parse(string $raw): ParsedMessage
    {
        [$headers, $body] = $this->splitHeaderBody($raw);
        $headerMap = $this->parseHeaders($headers);

        $subject = $this->decodeHeader($headerMap['subject'] ?? '');

        $attachments = [];
        $this->walkEntity($headerMap, $body, $attachments);

        return new ParsedMessage($subject, $attachments);
    }

    /**
     * Декодирует RFC2047 encoded-word в заголовке (например "=?UTF-8?B?...?=").
     */
    public function decodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }

    /**
     * @param Attachment[] $attachments
     */
    private function walkEntity(array $headers, string $body, array &$attachments): void
    {
        $contentType = $this->parseContentType($headers['content-type'] ?? 'text/plain');
        $type = $contentType['type'];
        $params = $contentType['params'];

        if (str_starts_with($type, 'multipart/')) {
            $boundary = $params['boundary'] ?? null;

            if ($boundary === null || $boundary === '') {
                return;
            }

            foreach ($this->splitMultipart($body, $boundary) as $part) {
                [$partHeaders, $partBody] = $this->splitHeaderBody($part);
                $this->walkEntity($this->parseHeaders($partHeaders), $partBody, $attachments);
            }

            return;
        }

        // leaf-часть: берём только части с Content-Disposition и именем файла (как в python-скрипте)
        $disposition = $headers['content-disposition'] ?? null;

        if ($disposition === null || $disposition === '') {
            return;
        }

        $filename = $this->extractFilename($disposition);

        if ($filename === null || $filename === '') {
            return;
        }

        $content = $this->decodeBody($body, $headers['content-transfer-encoding'] ?? '7bit');

        if ($content === '') {
            return;
        }

        $attachments[] = new Attachment($filename, $content);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitHeaderBody(string $entity): array
    {
        if (($pos = strpos($entity, "\r\n\r\n")) !== false) {
            return [substr($entity, 0, $pos), substr($entity, $pos + 4)];
        }

        if (($pos = strpos($entity, "\n\n")) !== false) {
            return [substr($entity, 0, $pos), substr($entity, $pos + 2)];
        }

        return [$entity, ''];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $headerBlock): array
    {
        $headers = [];

        // раскрываем folded-строки: CRLF + пробел/таб → пробел
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $headerBlock);

        foreach (preg_split("/\r?\n/", (string) $unfolded) as $line) {
            if ($line === '') {
                continue;
            }

            $pos = strpos($line, ':');

            if ($pos === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));

            if (isset($headers[$name])) {
                $headers[$name] .= ', ' . $value;
            } else {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @return array{type: string, params: array<string, string>}
     */
    private function parseContentType(string $value): array
    {
        $parts = explode(';', $value);
        $type = strtolower(trim(array_shift($parts)));
        $params = [];

        foreach ($parts as $part) {
            if (preg_match('/^\s*([^=]+)=\s*"?([^";]*)"?/', $part, $m)) {
                $params[strtolower(trim($m[1]))] = trim($m[2], " \t\"");
            }
        }

        return ['type' => $type, 'params' => $params];
    }

    /**
     * @return string[]
     */
    private function splitMultipart(string $body, string $boundary): array
    {
        $quoted = preg_quote($boundary, '/');
        $pattern = '/(?:^|\r?\n)--' . $quoted . '(?:--)?[ \t]*\r?\n/';

        $parts = preg_split($pattern, $body);

        if ($parts === false) {
            return [];
        }

        // первый элемент — преамбула, последний — эпилог (обычно пустые)
        if (count($parts) > 1) {
            array_shift($parts);
            array_pop($parts);
        } else {
            return [];
        }

        return $parts;
    }

    private function extractFilename(string $disposition): ?string
    {
        // RFC2231: filename*=UTF-8''... (в т.ч. продолжения filename*0*, filename*1*)
        $starParts = [];

        if (preg_match_all('/(?:^|;)\s*filename\*(\d*)\*\s*=\s*([^;]*)/i', $disposition, $matches, PREG_SET_ORDER)) {
            $hasStar = false;

            foreach ($matches as $m) {
                $hasStar = true;
                $idx = $m[1] === '' ? 0 : (int) $m[1];
                $starParts[$idx] = $m[2];
            }

            if ($hasStar) {
                ksort($starParts);

                return $this->decodeRfc2231(implode('', $starParts));
            }
        }

        if (preg_match('/filename\s*=\s*"([^"]*)"/i', $disposition, $m)) {
            return $this->decodeHeader($m[1]);
        }

        if (preg_match('/filename\s*=\s*([^;\s]+)/i', $disposition, $m)) {
            return $this->decodeHeader(trim($m[1], " \t\""));
        }

        return null;
    }

    private function decodeRfc2231(string $value): string
    {
        // формат: charset'lang'percent-encoded
        if (preg_match("/^([^']*)'([^']*)'(.*)$/", $value, $m)) {
            $encoded = $m[3];

            return rawurldecode($encoded);
        }

        return rawurldecode($value);
    }

    private function decodeBody(string $body, string $encoding): string
    {
        $encoding = strtolower(trim($encoding));

        return match ($encoding) {
            'base64' => $this->decodeBase64($body),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function decodeBase64(string $body): string
    {
        $clean = preg_replace('/\s+/', '', $body);
        $decoded = base64_decode($clean ?? '', true);

        if ($decoded === false) {
            $decoded = base64_decode($clean ?? '', false);
        }

        return $decoded === false ? '' : $decoded;
    }
}
