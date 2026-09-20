<?php

declare(strict_types=1);

namespace App\BankAccount;

/**
 * Port of BonPrelevement::buildRumNumber()
 * (htdocs/compta/prelevement/class/bonprelevement.class.php).
 *
 * Upstream builds the RUM ("référence unique de mandat" / SEPA mandate id)
 * as: <prefix>-<yymmddHHMM>-<ribid>[-<code_client>] truncated to 17 chars.
 * The prefix is the translation of 'RUM' ('UMR' in French); the service has
 * no translation layer, so the English 'RUM' is used.
 */
final class RumGenerator
{
    private const PREFIX = 'RUM';

    public function buildRumNumber(?string $codeClient, \DateTimeInterface|int|string $datec, string|int $ribId): string
    {
        $timestamp = $this->toTimestamp($datec);

        $datepart = (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('ymdHi');

        return self::PREFIX . '-' . $datepart . '-' . mb_substr(
            (string) $ribId . ($codeClient ? '-' . $codeClient : ''),
            0,
            17,
            'UTF-8',
        );
    }

    private function toTimestamp(\DateTimeInterface|int|string $datec): int
    {
        if ($datec instanceof \DateTimeInterface) {
            return $datec->getTimestamp();
        }
        if (is_int($datec) || ctype_digit((string) $datec)) {
            return (int) $datec;
        }

        return (int) strtotime($datec);
    }
}
