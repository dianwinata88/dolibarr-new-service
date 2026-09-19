<?php

declare(strict_types=1);

namespace App\BankAccount;

use App\BankAccount\Iban\Iban;
use App\Entity\Dictionary\Country;
use App\Entity\Societe;
use App\Entity\SocieteRib;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Port of CompanyBankAccount/Account verification helpers:
 * Account::verif() (htdocs/compta/bank/class/account.class.php) and
 * checkIbanForAccount() / checkSwiftForAccount() / checkBanForAccount() /
 * checkES() / Account::getCountryCode() (htdocs/core/lib/bank.lib.php).
 *
 * Upstream does NOT call verif() from the bankaccounts REST endpoints — it is
 * used by the payment-modes UI and withdrawal flows as a validity indicator —
 * so this service is provided for parity but is not enforced on write.
 */
final class RibVerifier
{
    public const STATUS_OPEN = 0;
    public const STATUS_CLOSED = 1;

    private string $lastError = '';

    public function __construct(
        private readonly Iban $iban,
        private readonly DolCrypt $dolCrypt,
        private readonly EntityManagerInterface $em,
        // port of getDolGlobalInt('WITHDRAWAL_WITHOUT_BIC')
        #[Autowire('%env(default::WITHDRAWAL_WITHOUT_BIC)%')]
        private readonly ?string $withdrawalWithoutBic = null,
    ) {
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Port of Account::verif().
     *
     * @return int 1 if correct, <=0 if wrong
     */
    public function verif(SocieteRib $rib): int
    {
        $error = 0;

        // Call functions to check BAN
        if (!$this->checkIbanForAccount($rib)) {
            $error++;
            $this->lastError = 'IBANNotValid';
        }
        // Check Swift/BIC. A non valid BIC/Swift is a problem if: it is not
        // empty or always a problem if WITHDRAWAL_WITHOUT_BIC is not set.
        if (!$this->checkSwiftForAccount($rib) && (!empty($rib->getBic()) || !$this->withdrawalWithoutBic)) {
            $error++;
            $this->lastError = 'SwiftNotValid';
        }

        return $error === 0 ? 1 : 0;
    }

    /**
     * Port of checkSwiftForAccount().
     */
    public function checkSwiftForAccount(?SocieteRib $rib = null, ?string $swift = null): bool
    {
        if ($rib === null && $swift === null) {
            return false;
        }
        if ($swift === null) {
            $swift = $rib?->getBic() ?? '';
        }

        return (bool) preg_match('/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/', $swift);
    }

    /**
     * Port of checkIbanForAccount().
     */
    public function checkIbanForAccount(?SocieteRib $rib = null, ?string $ibantocheck = null): bool
    {
        if ($rib === null && $ibantocheck === null) {
            return false;
        }
        if ($ibantocheck === null) {
            // upstream reads the already-decrypted ->iban property
            $ibantocheck = $this->dolCrypt->decrypt($rib?->getIbanPrefix() ?? '');
        }

        return $this->iban->verify($ibantocheck);
    }

    /**
     * Port of checkBanForAccount() — country-specific BAN rules (FR, ES, AU)
     * plus the generic "number not empty" fallback.
     */
    public function checkBanForAccount(SocieteRib $rib): bool
    {
        $countryCode = $this->getCountryCode($rib);

        if ($countryCode === 'FR') { // France rules
            $coef = [62, 34, 3];
            // Concatenate the code parts
            $ban = strtolower(
                trim((string) $rib->getCodeBanque())
                . trim((string) $rib->getCodeGuichet())
                . trim((string) $rib->getNumber())
                . trim((string) $rib->getCleRib()),
            );
            // Replace any letters with numbers
            $ban = strtr($ban, 'abcdefghijklmnopqrstuvwxyz', '12345678912345678923456789');
            // Split the rib into 3 groups of 7 + 1 group of 2; multiply each
            // group by the coefficients.
            $s = 0;
            for ($i = 0; $i < 3; $i++) {
                $code = substr($ban, 7 * $i, 7);
                $s += ((int) $code) * $coef[$i];
            }
            // Subtract modulo 97 of $s from 97 to get the key
            $cleRib = 97 - ($s % 97);

            return $cleRib == $rib->getCleRib();
        }

        if ($countryCode === 'ES') { // Spanish rules
            $ccc = strtolower(trim((string) $rib->getNumber()));
            $ban = strtolower(
                trim((string) $rib->getCodeBanque()) . trim((string) $rib->getCodeGuichet()),
            );
            $cleRib = strtolower($this->checkES($ban, $ccc));

            return $cleRib === strtolower((string) $rib->getCleRib());
        }

        if ($countryCode === 'AU') { // Australian
            $len = strlen((string) $rib->getCodeBanque());
            if ($len > 7) {
                return false; // Should be 6 but can be 123-456
            }

            return $len >= 6; // Should be 6
        }

        // No particular rule
        return !empty($rib->getNumber());
    }

    /**
     * Port of Account::getCountryCode().
     */
    public function getCountryCode(SocieteRib $rib): string
    {
        // Country code stored on the bank account
        if (!empty($rib->getCountryCode())) {
            return (string) $rib->getCountryCode();
        }

        // Guess country from the IBAN
        $iban = $this->dolCrypt->decrypt($rib->getIbanPrefix() ?? '');
        if ($iban !== '') {
            $reg = [];
            if (preg_match('/^([a-zA-Z][a-zA-Z])/i', $iban, $reg)) {
                return $reg[1];
            }
        }

        // Country of the linked third party
        if ($rib->getFkSoc() > 0) {
            $company = $this->em->find(Societe::class, $rib->getFkSoc());
            if ($company instanceof Societe && $company->getFkPays()) {
                $country = $this->em->find(Country::class, $company->getFkPays());
                if ($country instanceof Country && $country->getCode() !== '') {
                    return $country->getCode();
                }
            }
        }

        return '';
    }

    /**
     * Port of checkES() — key for Spanish bank accounts.
     */
    private function checkES(string $ientOfi, string $inumCta): string
    {
        if ($ientOfi === '' || $inumCta === '' || strlen($ientOfi) !== 8 || strlen($inumCta) !== 10) {
            return '';
        }

        $ccc = $ientOfi . $inumCta;
        $numbers = '1234567890';

        for ($i = 0; $i < strlen($ccc); $i++) {
            if (!str_contains($numbers, substr($ccc, $i, 1))) {
                return '';
            }
        }

        $values = [1, 2, 4, 8, 5, 10, 9, 7, 3, 6];
        $sum = 0;
        for ($i = 2; $i < 10; $i++) {
            $sum += $values[$i] * (int) substr($ientOfi, $i - 2, 1);
        }
        $key = 11 - $sum % 11;
        if ($key === 10) {
            $key = 1;
        }
        if ($key === 11) {
            $key = 0;
        }
        $keycontrol = (string) $key;

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $values[$i] * (int) substr($inumCta, $i, 1);
        }
        $key = 11 - $sum % 11;
        if ($key === 10) {
            $key = 1;
        }
        if ($key === 11) {
            $key = 0;
        }
        $keycontrol .= (string) $key;

        return $keycontrol;
    }
}
