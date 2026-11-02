<?php

namespace App\Service\Imap;

/**
 * Вложение письма (leaf-часть с именем файла).
 */
class Attachment
{
    public function __construct(
        public string $filename,
        public string $content,
    ) {
    }
}
