<?php

declare(strict_types=1);

namespace App\ThirdParty;

/**
 * Verbatim port of profid.lib.php — professional identifier validation
 * (SIREN/SIRET Luhn, ES CIF/NIF/NIE, PT/DZ/BE TIN) and
 * isValidProfIds() country dispatch.
 */
final class ProfidValidator
{
    public function __construct(private readonly DolibarrConfig $config)
    {
    }

    public function isValidLuhn(string $str): bool
    {
        $len = strlen($str);
        $parity = $len % 2;
        $sum = 0;
        for ($i = $len - 1; $i >= 0; $i--) {
            $d = (int) $str[$i];
            if ($i % 2 == $parity) {
                if (($d *= 2) > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $sum % 10 == 0;
    }

    public function isValidSiren(string $siren, bool $lengthonly = false): bool
    {
        $siren = trim($siren);
        $siren = (string) preg_replace('/(\s)/', '', $siren);

        if (!is_numeric($siren) || strlen($siren) != 9) {
            return false;
        }

        return $lengthonly || $this->isValidLuhn($siren);
    }

    public function isValidSiret(string $siret, bool $lengthonly = false): bool
    {
        $siret = trim($siret);
        $siret = (string) preg_replace('/(\s)/', '', $siret);

        if (!is_numeric($siret) || strlen($siret) != 14) {
            return false;
        }

        if ($lengthonly || $this->isValidLuhn($siret)) {
            return true;
        }
        // "La Poste" specific rule
        return substr($siret, 0, 9) === '356000000' && (array_sum(str_split($siret)) % 5 == 0);
    }

    public function isValidTinForPT(string $str): bool
    {
        $str = (string) preg_replace('/(\s)/', '', trim($str));

        return (bool) preg_match('/(^[0-9]{9}$)/', $str);
    }

    public function isValidTinForDZ(string $str): bool
    {
        $str = (string) preg_replace('/(\s)/', '', trim($str));

        return (bool) preg_match('/(^[0-9]{15}$)/', $str);
    }

    public function isValidTinForBE(string $str): bool
    {
        $str = (string) preg_replace('/(\s)/', '', trim($str));

        return (bool) preg_match('/(^[0-1]{1}[0-9]{3}\.[0-9]{3}\.[0-9]{3}$)/', $str);
    }

    /**
     * @return int 1 if NIF ok, 2 if CIF ok, 3 if NIE ok,
     *             -1/-2/-3 if bad, -4 unverifiable, 0 bad format
     */
    public function isValidTinForES(string $str): int
    {
        $str = strtoupper((string) preg_replace('/(\s)/', '', trim($str)));

        if (!preg_match('/((^[A-Z]{1}[0-9]{7}[A-Z0-9]{1}$|^[T]{1}[A-Z0-9]{8}$)|^[0-9]{8}[A-Z]{1}$)/', $str)) {
            return 0;
        }

        $num = [];
        for ($i = 0; $i < 9; $i++) {
            $num[$i] = substr($str, $i, 1);
        }

        // NIF
        if (preg_match('/(^[0-9]{8}[A-Z]{1}$)/', $str)) {
            return $num[8] === substr('TRWAGMYFPDXBNJZSQVHLCKE', (int) substr($str, 0, 8) % 23, 1) ? 1 : -1;
        }

        // CIF/NIE algorithm sum
        $sum = (int) $num[2] + (int) $num[4] + (int) $num[6];
        for ($i = 1; $i < 8; $i += 2) {
            $sum += (int) substr((string) (2 * (int) $num[$i]), 0, 1) + (int) substr((string) (2 * (int) $num[$i]), 1, 1);
        }
        $n = 10 - (int) substr((string) $sum, strlen((string) $sum) - 1, 1);

        // special NIF
        if (preg_match('/^[KLM]{1}/', $str)) {
            return ($num[8] === chr(64 + $n) || $num[8] === substr('TRWAGMYFPDXBNJZSQVHLCKE', (int) substr($str, 1, 8) % 23, 1)) ? 1 : -1;
        }

        // CIF
        if (preg_match('/^[ABCDEFGHJNPQRSUVW]{1}/', $str)) {
            return ($num[8] === chr(64 + $n) || $num[8] === substr((string) $n, strlen((string) $n) - 1, 1)) ? 2 : -2;
        }

        // NIE T
        if (preg_match('/^[T]{1}/', $str)) {
            return preg_match('/^[T]{1}[A-Z0-9]{8}$/', $str) ? 3 : -3;
        }

        // NIE XYZ
        if (preg_match('/^[XYZ]{1}/', $str)) {
            return $num[8] === substr('TRWAGMYFPDXBNJZSQVHLCKE', (int) substr(str_replace(['X', 'Y', 'Z'], ['0', '1', '2'], $str), 0, 8) % 23, 1) ? 3 : -3;
        }

        return -4;
    }

    /**
     * Port of isValidProfIds(): country-aware professional id check.
     *
     * Returns <=0 KO, >0 OK.
     *
     * @return int
     */
    public function isValidProfIds(int $idprof, Company $thirdparty, bool $lenghtonly = false): int
    {
        if ($this->config->getString('MAIN_DISABLEPROFIDRULES')) {
            return 1;
        }

        if (($thirdparty->country_code ?? '') === 'FR') {
            if ($idprof === 1 && !$this->isValidSiren((string) $thirdparty->idprof1, $lenghtonly)) {
                return -1;
            }
            if ($idprof === 2 && !$this->isValidSiret((string) $thirdparty->idprof2, $lenghtonly)) {
                return -2;
            }
        }

        if ($idprof === 1 && ($thirdparty->country_code ?? '') === 'ES') {
            return $this->isValidTinForES((string) $thirdparty->idprof1);
        }
        if ($idprof === 1 && ($thirdparty->country_code ?? '') === 'PT' && !$this->isValidTinForPT((string) $thirdparty->idprof1)) {
            return -1;
        }
        if ($idprof === 1 && ($thirdparty->country_code ?? '') === 'DZ' && !$this->isValidTinForDZ((string) $thirdparty->idprof1)) {
            return -1;
        }
        if ($idprof === 1 && ($thirdparty->country_code ?? '') === 'BE' && !$this->isValidTinForBE((string) $thirdparty->idprof1)) {
            return -1;
        }

        return 1;
    }

    /** Port of id_prof_check(). */
    public function idProfCheck(int $idprof, Company $company): int
    {
        return $this->isValidProfIds($idprof, $company);
    }

    /** Port of isProfIdWithoutSpace(). */
    public function isProfIdWithoutSpace(int $idprof, string $countryCode): bool
    {
        $idprofwithoutspace = [
            'FR' => [1, 2],
            'ES' => [1],
            'PT' => [1],
            'DZ' => [1],
            'BE' => [1],
        ];

        $countryCode = strtoupper($countryCode);

        return isset($idprofwithoutspace[$countryCode]) && in_array($idprof, $idprofwithoutspace[$countryCode], true);
    }
}
