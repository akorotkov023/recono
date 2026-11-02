<?php

declare(strict_types=1);

namespace App\Enum;

final class RegistryCommandStatusEnum
{
    public const NEW = 'new';
    public const EXTRACTED = 'extracted';
    public const VERIFIED = 'verified';
    public const ISSUES = 'issues';
    public const ERROR = 'error';

    /**
     * Все доступные статусы
     */
    public static function all(): array
    {
        return [
            self::NEW,
            self::EXTRACTED,
            self::VERIFIED,
            self::ISSUES,
            self::ERROR,
        ];
    }

    /**
     * Финальные статусы
     */
    public static function final(): array
    {
        return [
            self::VERIFIED,
            self::ERROR,
        ];
    }

    /**
     * Статусы с ошибками
     */
    public static function error(): array
    {
        return [
            self::ERROR,
            self::ISSUES,
        ];
    }
}
