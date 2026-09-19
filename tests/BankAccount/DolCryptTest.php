<?php

declare(strict_types=1);

namespace App\Tests\BankAccount;

use App\BankAccount\DolCrypt;
use PHPUnit\Framework\TestCase;

/**
 * Port coverage for dolEncrypt()/dolDecrypt()
 * (htdocs/blockedlog/lib/securitycore.lib.php).
 */
final class DolCryptTest extends TestCase
{
    public function testEmptyInputIsPassThrough(): void
    {
        $crypt = new DolCrypt('instance-key');
        self::assertSame('', $crypt->encrypt(''));
        self::assertSame('', $crypt->encrypt(null));
        self::assertSame('', $crypt->decrypt(''));
        self::assertSame('', $crypt->decrypt(null));
    }

    public function testEmptyKeyIsPassThrough(): void
    {
        // upstream: no instance_unique_id -> values stored in clear
        $crypt = new DolCrypt(null);
        self::assertSame('FR7630001007941234567890185', $crypt->encrypt('FR7630001007941234567890185'));
        self::assertSame('FR7630001007941234567890185', $crypt->decrypt('FR7630001007941234567890185'));
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $crypt = new DolCrypt('test-instance-unique-id');
        $encrypted = $crypt->encrypt('FR7630001007941234567890185');

        self::assertMatchesRegularExpression('/^dolcrypt:AES-256-CTR:/', $encrypted);
        self::assertSame('FR7630001007941234567890185', $crypt->decrypt($encrypted));
    }

    public function testAlreadyEncryptedInputIsReturnedAsIs(): void
    {
        $crypt = new DolCrypt('k');
        $encrypted = 'dolcrypt:AES-256-CTR:iv:AAAA';
        self::assertSame($encrypted, $crypt->encrypt($encrypted));
    }

    public function testDecryptWithMultiKeyList(): void
    {
        $writer = new DolCrypt('first-key');
        $encrypted = $writer->encrypt('secret-value');

        // decryption tries each comma-separated key like upstream
        $reader = new DolCrypt('wrong-key,first-key');
        self::assertSame('secret-value', $reader->decrypt($encrypted));
    }

    public function testDecryptGarbageReturnsInput(): void
    {
        $crypt = new DolCrypt('k');
        self::assertSame('not-encrypted', $crypt->decrypt('not-encrypted'));
    }
}
