<?php

declare(strict_types=1);

namespace App\Tests\SalesRep;

use App\Aux\DolibarrContext;
use App\SalesRep\SalesRepService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit tests for the multicompany helpers: getEntity-equivalent semantics
 * (entity in (0, current)) and the llx_societe_commerciaux EXISTS filter.
 */
final class AuxHelpersTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        $keys = [
            'DOLIBARR_ENTITY', 'DOLIBARR_MODULES', 'MAIN_COMPANY_PERENTITY_SHARED',
            'HOLIDAY_ALLOW_ZERO_IN_DIC', 'MULTICOMPANY_TRANSVERSE_MODE',
        ];
        foreach ($keys as $k) {
            unset($_SERVER[$k], $_ENV[$k]);
        }
        parent::tearDown();
    }

    private function ctx(): DolibarrContext
    {
        self::bootKernel();

        return self::getContainer()->get(DolibarrContext::class);
    }

    public function testGetEntityWithoutMulticompany(): void
    {
        $_SERVER['DOLIBARR_ENTITY'] = $_ENV['DOLIBARR_ENTITY'] = '3';
        $ctx = $this->ctx();

        // regular elements: current entity only
        self::assertSame('3', $ctx->getEntity('societe'));
        // addzero elements (users shared on entity 0)
        self::assertSame('0,3', $ctx->getEntity('user'));
        self::assertSame('0,3', $ctx->getEntity('usergroup'));
        // upstream aliases
        self::assertSame('3', $ctx->getEntity('projet'));
        self::assertSame('3', $ctx->getEntity('contrat'));
        self::assertSame('3', $ctx->getEntity('order_supplier'));
        self::assertSame('3', $ctx->getEntity('invoice_supplier'));
    }

    public function testGetEntityWithMulticompanyShared(): void
    {
        $_SERVER['DOLIBARR_ENTITY'] = $_ENV['DOLIBARR_ENTITY'] = '5';
        $_SERVER['DOLIBARR_MODULES'] = $_ENV['DOLIBARR_MODULES'] = 'multicompany';
        $ctx = $this->ctx();

        self::assertSame('0,5', $ctx->getEntity('societe'));
        self::assertSame('5', $ctx->getEntity('societe', 0));
    }

    public function testEntityDefaultsToOne(): void
    {
        unset($_SERVER['DOLIBARR_ENTITY'], $_ENV['DOLIBARR_ENTITY']);
        self::assertSame(1, $this->ctx()->entity());
    }

    public function testSalesRepresentativeSqlFilter(): void
    {
        self::bootKernel();
        /** @var SalesRepService $service */
        $service = self::getContainer()->get(SalesRepService::class);

        self::assertSame(
            'EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE s.rowid = sc.fk_soc)',
            $service->salesRepresentativeSqlFilter('s.rowid'),
        );
        self::assertSame(
            'NOT EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc'
                . ' WHERE s.rowid = sc.fk_soc AND sc.fk_user IN (4,7))',
            $service->salesRepresentativeSqlFilter('s.rowid', '4,-1,abc,7', 1),
        );
    }
}
