<?php

declare(strict_types=1);

namespace App\Tests\Pricing;

use App\Pricing\Util\Price2Num;
use PHPUnit\Framework\TestCase;

final class Price2NumTest extends TestCase
{
    public function testNumericInputPassesThrough(): void
    {
        self::assertSame(1.5, Price2Num::num(1.5));
        self::assertSame(100.0, Price2Num::num('100'));
        self::assertSame(0.0, Price2Num::num(null));
        self::assertSame(0.0, Price2Num::num(''));
    }

    public function testGarbageIsStrippedLikeUpstream(): void
    {
        // Letters, parentheses (vat code), thousands separators removed
        self::assertSame(20.0, Price2Num::num('20 (ABC)'));
        self::assertSame(1234.56, Price2Num::num('1,234.56'));
        self::assertSame(1234.56, Price2Num::num('1 234.56'));
    }

    public function testRoundingModes(): void
    {
        // MT = MAIN_MAX_DECIMALS_TOT default (2)
        self::assertSame(16.67, Price2Num::num(100 / 6, 'MT'));
        // MU = MAIN_MAX_DECIMALS_UNIT default (5)
        self::assertSame(16.66667, Price2Num::num(100 / 6, 'MU'));
        // numeric rounding
        self::assertSame(16.7, Price2Num::num(100 / 6, '1'));
        // no rounding
        self::assertSame(100 / 6, Price2Num::num(100 / 6, ''));
    }
}
