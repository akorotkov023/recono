<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Стратегии формирования реестров мерчантам (поле email_config.registry_type).
 */
final class RegistryTypeEnum
{
    /** Комиссия процентом от суммы (0.4%), поле fee, refund и спец-формат пустого реестра. */
    public const PERCENT = 'percent';

    /** Зачисление берётся из provider_amount. */
    public const PROVIDER_AMOUNT = 'provider_amount';

    /** Зачисление берётся из merchant_amount. */
    public const MERCHANT_AMOUNT = 'merchant_amount';

    /**
     * Все доступные значения.
     */
    public static function all(): array
    {
        return [
            self::PERCENT,
            self::PROVIDER_AMOUNT,
            self::MERCHANT_AMOUNT,
        ];
    }
}
