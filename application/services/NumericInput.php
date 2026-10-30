<?php
namespace App\Services;

use App\Exceptions\ValidationException;

/** Validate untrusted numeric values before any lossy casts or arithmetic. */
final class NumericInput
{
    public const MAX_QUANTITY = 2147483647;
    public const MAX_MONEY = 999999999999; // DECIMAL(12,2), in halalas.

    public static function integer($value, int $minimum = 1, int $maximum = PHP_INT_MAX): int
    {
        if (!is_int($value) && !is_string($value)) { throw new ValidationException('Invalid integer value.'); }
        $text = (string) $value;
        if (!preg_match('/^[0-9]+$/D', $text)) { throw new ValidationException('Invalid integer value.'); }
        $text = ltrim($text, '0') ?: '0';
        $limit = (string) $maximum;
        if (strlen($text) > strlen($limit) || (strlen($text) === strlen($limit) && strcmp($text, $limit) > 0)) {
            throw new ValidationException('Integer value exceeds the allowed limit.');
        }
        $number = (int) $text;
        if ($number < $minimum) { throw new ValidationException('Integer value is below the allowed limit.'); }
        return $number;
    }

    public static function hundredths($value, int $maximum): int
    {
        if (!is_int($value) && !is_string($value)) { throw new ValidationException('Invalid decimal value.'); }
        if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', (string) $value, $parts)) {
            throw new ValidationException('Invalid decimal value.');
        }
        $whole = self::integer($parts[1], 0, intdiv($maximum, 100));
        $number = $whole * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
        if ($number > $maximum) { throw new ValidationException('Decimal value exceeds the allowed limit.'); }
        return $number;
    }

    public static function decimal(int $halalas): string
    {
        return intdiv($halalas, 100) . '.' . str_pad((string) ($halalas % 100), 2, '0', STR_PAD_LEFT);
    }
}
