<?php

declare(strict_types=1);

namespace App\Tests\BankAccount;

use App\BankAccount\Iban\Iban;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Port coverage for htdocs/includes/php-iban/php-iban.php (verify_iban).
 */
final class IbanTest extends TestCase
{
    private Iban $iban;

    protected function setUp(): void
    {
        $this->iban = new Iban();
    }

    #[DataProvider('validIbans')]
    public function testValidIbansVerify(string $iban): void
    {
        self::assertTrue($this->iban->verify($iban));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validIbans(): iterable
    {
        yield 'FR' => ['FR7630001007941234567890185'];
        yield 'DE' => ['DE89370400440532013000'];
        yield 'GB' => ['GB29NWBK60161331926819'];
        yield 'ES' => ['ES9121000418450200051332'];
        yield 'machine-format with spaces' => ['FR76 3000 1007 9412 3456 7890 185'];
        yield 'lowercase' => ['fr7630001007941234567890185'];
        yield 'IBAN prefix' => ['IBAN DE89370400440532013000'];
    }

    #[DataProvider('invalidIbans')]
    public function testInvalidIbansDoNotVerify(string $iban): void
    {
        self::assertFalse($this->iban->verify($iban));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIbans(): iterable
    {
        yield 'empty' => [''];
        yield 'bad checksum' => ['FR7630001007941234567890184'];
        yield 'unknown country' => ['ZZ7630001007941234567890185'];
        yield 'too short' => ['FR76'];
        yield 'bad chars for country format' => ['FR763000100794123456789018!'];
        // MC has no fixed length in registry -> regex/format still checked
        yield 'unknown-length country' => ['MC932005222100100471M2337'];
    }

    public function testToMachineFormat(): void
    {
        self::assertSame(
            'FR7630001007941234567890185',
            $this->iban->toMachineFormat('FR76 3000 1007 9412 3456 7890 185'),
        );
        self::assertSame('DE89370400440532013000', $this->iban->toMachineFormat('iban de89 3704 0044 0532 0130 00'));
        self::assertSame('', $this->iban->toMachineFormat(''));
    }
}
