<?php

declare(strict_types=1);

namespace App\Tests\BankAccount;

use App\BankAccount\DolCrypt;
use App\BankAccount\Iban\Iban;
use App\BankAccount\RibVerifier;
use App\Entity\SocieteRib;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Port coverage for checkSwiftForAccount() / checkIbanForAccount() /
 * checkBanForAccount() / checkES() / Account::verif()
 * (htdocs/core/lib/bank.lib.php, account.class.php).
 */
final class RibVerifierTest extends TestCase
{
    private RibVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new RibVerifier(
            new Iban(),
            new DolCrypt(null),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    public function testValidSwift(): void
    {
        $rib = new SocieteRib();
        $rib->setBic('BNPAFRPPXXX');
        self::assertTrue($this->verifier->checkSwiftForAccount($rib));
        self::assertTrue($this->verifier->checkSwiftForAccount(null, 'BNPAFRPP'));
    }

    public function testInvalidSwift(): void
    {
        $rib = new SocieteRib();
        $rib->setBic('not-a-bic');
        self::assertFalse($this->verifier->checkSwiftForAccount($rib));
        self::assertFalse($this->verifier->checkSwiftForAccount());
    }

    public function testValidIbanOnEntity(): void
    {
        $rib = new SocieteRib();
        $rib->setIbanPrefix('FR7630001007941234567890185');
        self::assertTrue($this->verifier->checkIbanForAccount($rib));
        // upstream verif() also requires a valid BIC when WITHDRAWAL_WITHOUT_BIC is off
        $rib->setBic('BNPAFRPPXXX');
        self::assertTrue($this->verifier->verif($rib) === 1);
    }

    public function testInvalidIbanFailsVerif(): void
    {
        $rib = new SocieteRib();
        $rib->setIbanPrefix('FR7630001007941234567890184');
        $rib->setBic('BNPAFRPPXXX');
        self::assertSame(0, $this->verifier->verif($rib));
        self::assertSame('IBANNotValid', $this->verifier->getLastError());
    }

    public function testInvalidBicFailsVerif(): void
    {
        $rib = new SocieteRib();
        $rib->setIbanPrefix('FR7630001007941234567890185');
        $rib->setBic('bad');
        self::assertSame(0, $this->verifier->verif($rib));
        self::assertSame('SwiftNotValid', $this->verifier->getLastError());
    }

    public function testFrenchRibKeyCheck(): void
    {
        // FR RIB from the upstream test corpus: 20041 01005 0500013M026 06
        $rib = new SocieteRib();
        $rib->setCountryCode('FR');
        $rib->setCodeBanque('20041');
        $rib->setCodeGuichet('01005');
        $rib->setNumber('0500013M026');
        $rib->setCleRib('06');

        self::assertTrue($this->verifier->checkBanForAccount($rib));

        $rib->setCleRib('07');
        self::assertFalse($this->verifier->checkBanForAccount($rib));
    }

    public function testSpanishRibKeyCheck(): void
    {
        // ES9121000418450200051332: ent 2100, office 0418, dc 45, account 0200051332
        $rib = new SocieteRib();
        $rib->setCountryCode('ES');
        $rib->setCodeBanque('2100');
        $rib->setCodeGuichet('0418');
        $rib->setNumber('0200051332');
        $rib->setCleRib('45');

        self::assertTrue($this->verifier->checkBanForAccount($rib));

        $rib->setCleRib('44');
        self::assertFalse($this->verifier->checkBanForAccount($rib));
    }

    public function testGenericFallbackRequiresNumber(): void
    {
        $rib = new SocieteRib();
        $rib->setCountryCode('US');
        $rib->setNumber('');
        self::assertFalse($this->verifier->checkBanForAccount($rib));

        $rib->setNumber('12345');
        self::assertTrue($this->verifier->checkBanForAccount($rib));
    }

    public function testAustralianBankCodeLength(): void
    {
        $rib = new SocieteRib();
        $rib->setCountryCode('AU');
        $rib->setCodeBanque('123456');
        self::assertTrue($this->verifier->checkBanForAccount($rib));

        $rib->setCodeBanque('12345678');
        self::assertFalse($this->verifier->checkBanForAccount($rib));
    }

    public function testCountryCodeGuessedFromIban(): void
    {
        $rib = new SocieteRib();
        $rib->setFkSoc(0);
        $rib->setIbanPrefix('DE89370400440532013000');
        self::assertSame('DE', $this->verifier->getCountryCode($rib));
    }
}
