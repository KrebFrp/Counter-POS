<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public const MAX_CENTS = 9999999999;

    public static function cents(string $amount): int
    {
        if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount)) {
            throw new InvalidArgumentException('Invalid decimal amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(string $amount, ?string $currency = null): string
    {
        [$whole, $fraction] = explode('.', self::decimal(self::cents($amount)));

        return ($currency ?? config('pos.currency')).' '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }
}
