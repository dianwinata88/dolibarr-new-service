<?php

declare(strict_types=1);

namespace App\BankAccount\Iban;

/**
 * Verbatim port of upstream includes/php-iban/oophp-iban.php (PHP_IBAN\IBAN)
 * restricted to what Dolibarr calls: IBAN->Verify().
 *
 * Loads the upstream SWIFT registry verbatim from registry.txt
 * (htdocs/includes/php-iban/registry.txt, LGPLv3 — kept byte-identical
 * so country rules stay in sync with the monolith).
 */
final class Iban
{
    /** @var array<string, array{iban_format_regex: string, iban_length: string}>|null */
    private ?array $registry = null;

    private readonly string $registryPath;

    public function __construct(?string $registryPath = null)
    {
        $this->registryPath = $registryPath ?? __DIR__ . '/registry.txt';
    }

    /**
     * Port of verify_iban().
     */
    public function verify(string $iban): bool
    {
        // First convert to machine format.
        $iban = $this->toMachineFormat($iban);

        // Get country of IBAN
        $country = substr($iban, 0, 2);

        // Test length of IBAN (upstream returns false for unknown countries)
        $length = $this->countryInfo($country, 'iban_length');
        if (strlen($iban) != $length) {
            return false;
        }

        // Get country-specific IBAN format regex
        $regex = '/' . $this->countryInfo($country, 'iban_format_regex') . '/';

        // Check regex
        if (preg_match($regex, $iban)) {
            // Regex passed, check checksum
            if (!$this->verifyChecksum($iban)) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Port of iban_to_machine_format().
     */
    public function toMachineFormat(string $iban): string
    {
        // Uppercase and trim spaces from left
        $iban = ltrim(strtoupper($iban));
        // Remove IIBAN or IBAN from start of string, if present
        $iban = (string) preg_replace('/^I?IBAN/', '', $iban);
        // Remove all non basic roman letter / digit characters
        return (string) preg_replace('/[^a-zA-Z0-9]/', '', $iban);
    }

    /**
     * Port of iban_verify_checksum() (mod97-10, ISO 13616).
     */
    private function verifyChecksum(string $iban): bool
    {
        $iban = $this->toMachineFormat($iban);
        // move first 4 chars (countrycode and checksum) to the end of the string
        $tempiban = substr($iban, 4) . substr($iban, 0, 4);
        // substitute chars
        $tempiban = self::checksumStringReplace($tempiban);
        // mod97-10 — checkvalue of 1 indicates correct IBAN checksum
        return self::mod97($tempiban) === 1;
    }

    /**
     * Port of iban_checksum_string_replace().
     */
    private static function checksumStringReplace(string $s): string
    {
        $chars = range('A', 'Z');
        $values = [];
        foreach (range(10, 35) as $v) {
            $values[] = (string) $v;
        }

        return str_replace($chars, $values, $s);
    }

    /**
     * Port of iban_mod97_10_checksum()'s mod-97 walking (no GMP dependency;
     * returns the remainder, identical semantics to upstream).
     */
    private static function mod97(string $numeric): int
    {
        $checksum = (int) substr($numeric, 0, 1);
        $length = strlen($numeric);
        for ($position = 1; $position < $length; $position++) {
            $checksum *= 10;
            $checksum += (int) substr($numeric, $position, 1);
            $checksum %= 97;
        }

        return $checksum;
    }

    private function countryInfo(string $country, string $field): string|false
    {
        $this->loadRegistry();
        $country = strtoupper($country);
        if (isset($this->registry[$country][$field])) {
            return $this->registry[$country][$field];
        }

        return false;
    }

    private function loadRegistry(): void
    {
        if ($this->registry !== null) {
            return;
        }
        $this->registry = [];
        $data = (string) file_get_contents($this->registryPath);
        $lines = explode("\n", $data);
        array_shift($lines); // drop leading description line
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $cols = explode('|', $line);
            $this->registry[$cols[0]] = [
                'iban_format_regex' => $cols[9],
                'iban_length' => $cols[10],
            ];
        }
    }
}
