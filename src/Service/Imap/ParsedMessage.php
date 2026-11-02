<?php

namespace App\Service\Imap;

/**
 * Разобранное RFC822-письмо: тема и вложения.
 */
class ParsedMessage
{
    /**
     * @param Attachment[] $attachments
     */
    public function __construct(
        public string $subject,
        public array $attachments,
    ) {
    }
}
