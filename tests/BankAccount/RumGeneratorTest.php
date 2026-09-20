<?php

declare(strict_types=1);

namespace App\Tests\BankAccount;

use App\BankAccount\RumGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Port coverage for BonPrelevement::buildRumNumber()
 * (htdocs/compta/prelevement/class/bonprelevement.class.php):
 * 'RUM' . '-' . <yymmddHHMM of datec> . '-' . <ribid + '-' + code_client truncated to 17>.
 */
final class RumGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('UTC');
    }

    public function testRumFormatMatchesUpstream(): void
    {
        $generator = new RumGenerator();
        // 2025-09-19 04:08:00 UTC -> dayhourlogsmall = '2509190408'
        self::assertSame('RUM-2509190408-42-CUST-01', $generator->buildRumNumber('CUST-01', 1758254880, 42));
    }

    public function testSuffixIsTruncatedTo17Chars(): void
    {
        $generator = new RumGenerator();
        self::assertSame(
            'RUM-2509190408-7-CUSTOMER-LONG-C',
            $generator->buildRumNumber('CUSTOMER-LONG-CODE', 1758254880, 7),
        );
    }

    public function testRumWithoutClientCode(): void
    {
        $generator = new RumGenerator();
        self::assertSame('RUM-2509190408-55', $generator->buildRumNumber(null, 1758254880, 55));
        self::assertSame('RUM-2509190408-55', $generator->buildRumNumber('', 1758254880, 55));
    }
}
