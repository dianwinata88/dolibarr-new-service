<?php

declare(strict_types=1);

namespace App\Pricing\Util;

/**
 * Port of Dolibarr's price2num() (htdocs/core/lib/functions.lib.php).
 *
 * Upstream returns a locale-formatted numeric string; callers cast it back to
 * float for arithmetic and interpolate it into SQL. Returning a float here is
 * equivalent for both uses.
 *
 * Rounding modes (upstream MAIN_MAX_DECIMALS_* defaults from conf.class.php):
 *   ''  -> no rounding
 *   'MU' -> unit prices, 5 decimals
 *   'MT' -> totals, 2 decimals
 *   'MS' -> stock, 5 decimals
 *   'CU'/'CT' -> currency unit/totals, 5/2 decimals
 *   'CR' -> currency rates, 8 decimals
 *   numeric string -> that many decimals
 */
final class Price2Num
{
    private function __construct()
    {
    }

    public static function num(float|int|string|null $amount, string $rounding = ''): float
    {
        if ($amount === null) {
            $amount = '';
        }

        if (is_string($amount) && !is_numeric($amount)) {
            // Upstream strips letters/symbols then keeps only digits, '-' and '.'
            $amount = (string) preg_replace('/[a-zA-Z\/\\\\\*\(\)\<>\_]/', '', $amount);
            $amount = (string) preg_replace('/[^0-9\-\.]/', '', $amount);
        } elseif (is_string($amount)) {
            $amount = (string) preg_replace('/[^0-9\-\.]/', '', $amount);
        }

        $value = (float) $amount;

        $decimals = match (true) {
            $rounding === '' => null,
            $rounding === 'MU', $rounding === 'CU', $rounding === 'MS' => 5,
            $rounding === 'MT', $rounding === 'CT' => 2,
            $rounding === 'CR' => 8,
            is_numeric($rounding) => (int) $rounding,
            default => null,
        };

        return $decimals === null ? $value : round($value, $decimals);
    }
}
