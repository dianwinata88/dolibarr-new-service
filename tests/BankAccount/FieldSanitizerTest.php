<?php

declare(strict_types=1);

namespace App\Tests\BankAccount;

use App\BankAccount\FieldSanitizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Port coverage for DolibarrApi::_checkValForAPI() + sanitizeVal().
 */
final class FieldSanitizerTest extends TestCase
{
    private FieldSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new FieldSanitizer();
    }

    public function testAlphanohtmlStripsTags(): void
    {
        self::assertSame('Acme Corp', $this->sanitizer->sanitizeForUpdate('label', '<b>Acme</b> <i>Corp</i>'));
    }

    public function testAlphanohtmlStripsTraversal(): void
    {
        self::assertSame('etc/passwd', $this->sanitizer->sanitizeForUpdate('label', '../etc/passwd'));
    }

    public function testIntFieldsRejectNonNumeric(): void
    {
        // fk_country is 'integer' in upstream CompanyBankAccount::$fields
        self::assertSame('', $this->sanitizer->sanitizeForUpdate('fk_country', 'abc'));
        self::assertSame('42', $this->sanitizer->sanitizeForUpdate('fk_country', '42'));
        self::assertSame(42, $this->sanitizer->sanitizeForUpdate('fk_country', 42));
    }

    public function testVarcharFieldsAreAlphanohtml(): void
    {
        // 'default_rib' is smallint(6) upstream -> NOT matched by int* -> alphanohtml
        self::assertSame('1', $this->sanitizer->sanitizeForUpdate('default_rib', '1'));
        self::assertSame('abc', $this->sanitizer->sanitizeForUpdate('number', 'abc'));
    }

    public function testForbiddenFieldThrows400(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->sanitizer->sanitizeForUpdate('fields', 'x');
    }

    public function testForbiddenPropertyNameThrows400(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->sanitizer->sanitizeForUpdate('db', 'x');
    }

    public function testMalformedFieldNameThrows400(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->sanitizer->sanitizeForUpdate('bad name!', 'x');
    }

    public function testFkPrefixedUnknownFieldIsInt(): void
    {
        self::assertSame('', $this->sanitizer->sanitizeForUpdate('fk_whatever', 'nope'));
        self::assertSame('7', $this->sanitizer->sanitizeForUpdate('fk_whatever', '7'));
    }

    public function testCreatePathSanitizesEverythingAsAlphanohtml(): void
    {
        // upstream createCompanyBankAccount() passes literal 'extrafields' as
        // the field name, so even integer columns go through alphanohtml
        self::assertSame('abc', $this->sanitizer->sanitizeForCreate('abc'));
        self::assertSame('1', $this->sanitizer->sanitizeForCreate('1'));
        self::assertSame('clean', $this->sanitizer->sanitizeForCreate('<script>clean</script>'));
    }

    public function testArrayValuesAreSanitizedRecursively(): void
    {
        $out = $this->sanitizer->sanitizeForUpdate('note', ['fk_x' => '5', 'label' => '<b>a</b>']);
        self::assertSame(['fk_x' => '5', 'label' => 'a'], $out);
    }
}
