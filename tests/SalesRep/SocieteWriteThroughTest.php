<?php

declare(strict_types=1);

namespace App\Tests\SalesRep;

use App\Entity\Societe;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Write-through tests: company modify -> llx_societe_log row, and
 * llx_societe_perentity sync/upserts/delete gated on
 * MAIN_COMPANY_PERENTITY_SHARED — the upstream Societe side effects.
 */
final class SocieteWriteThroughTest extends KernelTestCase
{
    private Connection $db;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->em = self::getContainer()->get('doctrine')->getManager();

        foreach (['llx_societe_log', 'llx_societe_perentity', 'llx_societe_commerciaux', 'llx_societe'] as $table) {
            try {
                $this->db->executeStatement("DELETE FROM $table");
            } catch (\Throwable) {
            }
        }
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MAIN_COMPANY_PERENTITY_SHARED'], $_ENV['MAIN_COMPANY_PERENTITY_SHARED']);
        parent::tearDown();
    }

    private function newSociete(string $nom): Societe
    {
        $s = new Societe();
        $s->setNom($nom);
        $s->setEntity(1);
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    // ------------------------------------------------------------------

    public function testCompanyModifyWritesSocieteLogRow(): void
    {
        $s = $this->newSociete('LogCorp');

        // create does not log (upstream only logs on modify)
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM llx_societe_log'));

        $s->setNom('LogCorp Renamed');
        $this->em->flush();

        $row = $this->db->fetchAssociative('SELECT * FROM llx_societe_log');
        self::assertIsArray($row);
        self::assertSame($s->getRowid(), (int) $row['fk_soc']);
        self::assertSame('COMPANY_MODIFY', $row['label']);
        self::assertNotEmpty($row['datel']);
    }

    public function testPerEntitySyncOnUpdateWhenShared(): void
    {
        $_SERVER['MAIN_COMPANY_PERENTITY_SHARED'] = $_ENV['MAIN_COMPANY_PERENTITY_SHARED'] = '1';

        $s = $this->newSociete('SharedCorp');
        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM llx_societe_perentity'));

        $s->setNom('SharedCorp v2');
        $s->setFkAccount(5);
        $this->em->flush();

        $row = $this->db->fetchAssociative(
            'SELECT * FROM llx_societe_perentity WHERE fk_soc = ? AND entity = 1',
            [$s->getRowid()],
        );
        self::assertIsArray($row);
        self::assertSame(5, (int) $row['fk_account']);
    }

    public function testPerEntityNotWrittenWhenSharingOff(): void
    {
        $s = $this->newSociete('PlainCorp');
        $s->setNom('PlainCorp v2');
        $this->em->flush();

        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM llx_societe_perentity'));
    }

    public function testPerEntityUpsertHelpers(): void
    {
        $_SERVER['MAIN_COMPANY_PERENTITY_SHARED'] = $_ENV['MAIN_COMPANY_PERENTITY_SHARED'] = '1';

        $s = $this->newSociete('UpsertCorp');
        /** @var \App\Aux\SocietePerEntityService $perEntity */
        $perEntity = self::getContainer()->get(\App\Aux\SocietePerEntityService::class);

        // upsert on a missing row inserts it
        self::assertSame(1, $perEntity->setBankAccount($s->getRowid(), 12));
        self::assertSame(12, (int) $this->db->fetchOne(
            'SELECT fk_account FROM llx_societe_perentity WHERE fk_soc = ? AND entity = 1',
            [$s->getRowid()],
        ));

        // existing row is updated, not duplicated
        self::assertSame(1, $perEntity->setBankAccount($s->getRowid(), 34));
        self::assertSame(34, (int) $this->db->fetchOne(
            'SELECT fk_account FROM llx_societe_perentity WHERE fk_soc = ? AND entity = 1',
            [$s->getRowid()],
        ));
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM llx_societe_perentity'));

        // payment terms: cond_reglement per entity, deposit_percent global
        self::assertSame(1, $perEntity->setPaymentTerms($s->getRowid(), 3, 25.0));
        self::assertSame(3, (int) $this->db->fetchOne(
            'SELECT cond_reglement FROM llx_societe_perentity WHERE fk_soc = ? AND entity = 1',
            [$s->getRowid()],
        ));
        self::assertSame('25', (string) $this->db->fetchOne(
            'SELECT deposit_percent FROM llx_societe WHERE rowid = ?',
            [$s->getRowid()],
        ));

        // payment methods: mode_reglement per entity, llx_societe untouched
        self::assertSame(1, $perEntity->setPaymentMethods($s->getRowid(), 7));
        self::assertSame(7, (int) $this->db->fetchOne(
            'SELECT mode_reglement FROM llx_societe_perentity WHERE fk_soc = ? AND entity = 1',
            [$s->getRowid()],
        ));
        self::assertNull($this->db->fetchOne(
            'SELECT mode_reglement FROM llx_societe WHERE rowid = ?',
            [$s->getRowid()],
        ));
    }

    public function testPerEntityRowsDeletedOnRemoveWhenShared(): void
    {
        $_SERVER['MAIN_COMPANY_PERENTITY_SHARED'] = $_ENV['MAIN_COMPANY_PERENTITY_SHARED'] = '1';

        $s = $this->newSociete('GoneCorp');
        $rowid = $s->getRowid();
        $this->db->insert('llx_societe_perentity', ['fk_soc' => $rowid, 'entity' => 1, 'fk_account' => 9]);

        $this->em->remove($s);
        $this->em->flush();

        self::assertSame(0, (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM llx_societe_perentity WHERE fk_soc = ?',
            [$rowid],
        ));
    }
}
