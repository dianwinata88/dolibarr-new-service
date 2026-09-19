<?php

declare(strict_types=1);

namespace App\Tests\Pricing;

use App\Entity\Societe;
use App\Entity\SocieteRemiseExcept;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional coverage for the pricing/discount endpoints. Requires the
 * MariaDB test schema (dolibarr_crm_test) — created with:
 *   bin/console doctrine:database:create --env=test --if-not-exists
 *   bin/console doctrine:migrations:migrate --env=test --no-interaction
 */
final class PricingApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private function apiKey(): string
    {
        $keys = explode(',', (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? 'dolibarr-dev-key'));

        return trim($keys[0]);
    }

    /**
     * @return array{HTTP_DOLAPIKEY: string}
     */
    private function auth(): array
    {
        return ['HTTP_DOLAPIKEY' => $this->apiKey()];
    }

    /**
     * @return array{HTTP_DOLAPIKEY: string, CONTENT_TYPE: string}
     */
    private function authJson(): array
    {
        return ['HTTP_DOLAPIKEY' => $this->apiKey(), 'CONTENT_TYPE' => 'application/json'];
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        try {
            $conn = $this->em()->getConnection();
            $tables = ['llx_societe_prices', 'llx_societe_remise', 'llx_societe_remise_except', 'llx_societe'];
            foreach ($tables as $table) {
                $conn->executeStatement('DELETE FROM ' . $table);
            }
        } catch (\Throwable $e) {
            self::markTestSkipped('Test database not available: ' . $e->getMessage());
        }
    }

    private function createThirdparty(string $name = 'TestCo'): int
    {
        $em = $this->em();
        $soc = new Societe();
        $soc->setNom($name);
        $soc->setEntity(1);
        $em->persist($soc);
        $em->flush();

        return (int) $soc->getRowid();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedDiscount(int $socid, array $overrides = []): int
    {
        $em = $this->em();
        $d = new SocieteRemiseExcept();
        $d->setEntity(1);
        $d->setFkSoc($socid);
        $d->setDiscountType($overrides['discount_type'] ?? 0);
        $d->setDatec(new \DateTime());
        $d->setAmountHt($overrides['amount_ht'] ?? 100.0);
        $d->setAmountTva($overrides['amount_tva'] ?? 20.0);
        $d->setAmountTtc($overrides['amount_ttc'] ?? 120.0);
        $d->setTvaTx($overrides['tva_tx'] ?? 20.0);
        $d->setFkUser(1);
        $d->setDescription($overrides['description'] ?? 'test discount');
        $d->setFkFacture($overrides['fk_facture'] ?? null);
        $d->setFkFactureLine($overrides['fk_facture_line'] ?? null);
        $d->setFkFactureSource($overrides['fk_facture_source'] ?? null);
        $d->setMulticurrencyAmountHt($overrides['multicurrency_amount_ht'] ?? 100.0);
        $d->setMulticurrencyAmountTva($overrides['multicurrency_amount_tva'] ?? 20.0);
        $d->setMulticurrencyAmountTtc($overrides['multicurrency_amount_ttc'] ?? 120.0);
        $em->persist($d);
        $em->flush();

        return (int) $d->getRowid();
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(): array
    {
        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testSetPriceLevelUpdatesSocieteAndLogsHistory(): void
    {
        $socid = $this->createThirdparty();

        $this->client->request('PUT', "/api/thirdparties/{$socid}/setpricelevel/3", server: $this->auth());

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(3, $body['price_level']);

        $em = $this->em();
        $em->clear();
        self::assertSame(3, $em->find(Societe::class, $socid)?->getPriceLevel());

        $history = $em->getConnection()->fetchAllAssociative(
            'SELECT * FROM llx_societe_prices WHERE fk_soc = ?',
            [$socid]
        );
        self::assertCount(1, $history);
        self::assertEquals(3, $history[0]['price_level']);
        self::assertNotEmpty($history[0]['datec']);
    }

    public function testSetPriceLevelOutOfRange(): void
    {
        $socid = $this->createThirdparty();

        $this->client->request('PUT', "/api/thirdparties/{$socid}/setpricelevel/6", server: $this->auth());

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            'Price level must be between 1 and 5',
            $this->responseBody()['error']['message'] ?? null,
        );
    }

    public function testSetPriceLevelUnknownThirdparty(): void
    {
        // Upstream maps a missing thirdparty here to a 500 "Error fetching".
        $this->client->request('PUT', '/api/thirdparties/99999/setpricelevel/2', server: $this->auth());

        self::assertResponseStatusCodeSame(500);
    }

    public function testFixedAmountDiscountCreateHt(): void
    {
        $socid = $this->createThirdparty();

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $this->authJson(),
            content: json_encode([
                'amount' => 100,
                'description' => 'discount HT',
                'tva_tx' => 20,
                'vat_src_code' => 'ABC',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $id = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsInt($id);

        $row = $this->em()->getConnection()->fetchAssociative(
            'SELECT * FROM llx_societe_remise_except WHERE rowid = ?',
            [$id]
        );
        self::assertNotFalse($row);
        self::assertEquals(100.0, (float) $row['amount_ht']);
        self::assertEquals(20.0, (float) $row['amount_tva']);
        self::assertEquals(120.0, (float) $row['amount_ttc']);
        self::assertEquals(20.0, (float) $row['tva_tx']);
        self::assertSame('ABC', $row['vat_src_code']);
        self::assertEquals(0, $row['discount_type']);
        self::assertEquals(1, $row['entity']);
        self::assertNull($row['fk_facture_source']);
    }

    public function testFixedAmountDiscountCreateTtc(): void
    {
        $socid = $this->createThirdparty();

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $this->authJson(),
            content: json_encode([
                'amount' => 120,
                'description' => 'discount TTC',
                'tva_tx' => 20,
                'price_base_type' => 'TTC',
                'vat_src_code' => 'ABC',
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $id = json_decode((string) $this->client->getResponse()->getContent(), true);

        $row = $this->em()->getConnection()->fetchAssociative(
            'SELECT * FROM llx_societe_remise_except WHERE rowid = ?',
            [$id]
        );
        self::assertEquals(100.0, (float) $row['amount_ht']);
        self::assertEquals(20.0, (float) $row['amount_tva']);
        self::assertEquals(120.0, (float) $row['amount_ttc']);
    }

    public function testFixedAmountDiscountCreateWithoutVatCodeIgnoresRate(): void
    {
        $socid = $this->createThirdparty();

        // Upstream quirk: without vat_src_code the vat rate is not forwarded
        // and the discount lands with tva_tx = 0.
        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $this->authJson(),
            content: json_encode([
                'amount' => 50,
                'description' => 'no vat code',
                'tva_tx' => 20,
            ]) ?: '',
        );

        self::assertResponseStatusCodeSame(201);
        $id = json_decode((string) $this->client->getResponse()->getContent(), true);
        $row = $this->em()->getConnection()->fetchAssociative(
            'SELECT * FROM llx_societe_remise_except WHERE rowid = ?',
            [$id]
        );
        self::assertEquals(0.0, (float) $row['tva_tx']);
        self::assertEquals(50.0, (float) $row['amount_ttc']);
    }

    public function testFixedAmountDiscountValidation(): void
    {
        $socid = $this->createThirdparty();
        $server = $this->authJson();

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $server,
            content: json_encode(['description' => 'x']) ?: ''
        );
        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            'Missing required field: amount',
            $this->responseBody()['error']['message'] ?? null,
        );

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $server,
            content: json_encode(['amount' => -5, 'description' => 'x']) ?: ''
        );
        self::assertResponseStatusCodeSame(400);

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $server,
            content: json_encode(['amount' => 5, 'description' => 'x', 'price_base_type' => 'XX']) ?: ''
        );
        self::assertResponseStatusCodeSame(400);

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/fixedamountdiscounts",
            server: $server,
            content: json_encode(['amount' => 5, 'description' => 'x', 'discount_type' => 7]) ?: ''
        );
        self::assertResponseStatusCodeSame(400);

        $this->client->request(
            'POST',
            '/api/thirdparties/99999/fixedamountdiscounts',
            server: $server,
            content: json_encode(['amount' => 5, 'description' => 'x']) ?: ''
        );
        self::assertResponseStatusCodeSame(404);
    }

    public function testFixedAmountDiscountListFilters(): void
    {
        $socid = $this->createThirdparty();
        $this->seedDiscount($socid); // available
        $this->seedDiscount($socid, ['fk_facture' => 42]); // used

        $this->client->request('GET', "/api/thirdparties/{$socid}/fixedamountdiscounts", server: $this->auth());
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->responseBody());

        $this->client->request(
            'GET',
            "/api/thirdparties/{$socid}/fixedamountdiscounts?filter=available",
            server: $this->auth(),
        );
        $available = $this->responseBody();
        self::assertCount(1, $available);
        self::assertNull($available[0]['fk_facture']);

        $this->client->request(
            'GET',
            "/api/thirdparties/{$socid}/fixedamountdiscounts?filter=used",
            server: $this->auth(),
        );
        $used = $this->responseBody();
        self::assertCount(1, $used);
        self::assertEquals(42, $used[0]['fk_facture']);
    }

    public function testAvailableDiscountsTotal(): void
    {
        $socid = $this->createThirdparty();
        $this->seedDiscount($socid, ['amount_ttc' => 120.0]);
        $this->seedDiscount($socid, ['amount_ttc' => 60.0, 'fk_facture' => 7]);

        $this->client->request('GET', "/api/thirdparties/{$socid}/availablediscounts", server: $this->auth());

        self::assertResponseIsSuccessful();
        self::assertEquals(120.0, $this->responseBody()['amount']);
    }

    public function testSplitDiscount(): void
    {
        $socid = $this->createThirdparty();
        $did = $this->seedDiscount($socid, [
            'amount_ht' => 100.0,
            'amount_tva' => 20.0,
            'amount_ttc' => 120.0,
            'tva_tx' => 20.0,
        ]);

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/splitdiscount/{$did}?amount_ttc_1=50&amount_ttc_2=70",
            server: $this->auth()
        );

        self::assertResponseIsSuccessful();
        $parts = $this->responseBody();
        self::assertCount(2, $parts);
        self::assertEquals(50.0, (float) $parts[0]['amount_ttc']);
        self::assertEquals(70.0, (float) $parts[1]['amount_ttc']);
        self::assertStringEndsWith('(1)', (string) $parts[0]['description']);
        self::assertStringEndsWith('(2)', (string) $parts[1]['description']);

        // original row is gone
        self::assertFalse($this->em()->getConnection()->fetchAssociative(
            'SELECT rowid FROM llx_societe_remise_except WHERE rowid = ?',
            [$did]
        ));

        // ht/tva recomputed from ttc at the same vat rate
        self::assertEquals(41.67, (float) $parts[0]['amount_ht']);
        self::assertEquals(8.33, (float) $parts[0]['amount_tva']);
    }

    public function testSplitDiscountRejectsBadSum(): void
    {
        $socid = $this->createThirdparty();
        $did = $this->seedDiscount($socid);

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/splitdiscount/{$did}?amount_ttc_1=10&amount_ttc_2=10",
            server: $this->auth()
        );

        self::assertResponseStatusCodeSame(405);
    }

    public function testDeleteFixedAmountDiscount(): void
    {
        $socid = $this->createThirdparty();
        $did = $this->seedDiscount($socid);
        $usedId = $this->seedDiscount($socid, ['fk_facture' => 9]);

        // Used discount survives the guarded delete (upstream: query succeeds,
        // zero rows match), family-wide delete only when unused.
        $this->client->request(
            'DELETE',
            "/api/thirdparties/{$socid}/fixedamountdiscounts/{$usedId}",
            server: $this->auth(),
        );
        self::assertResponseIsSuccessful();

        $this->client->request(
            'DELETE',
            "/api/thirdparties/{$socid}/fixedamountdiscounts/{$did}",
            server: $this->auth(),
        );
        self::assertResponseIsSuccessful();

        self::assertFalse($this->em()->getConnection()->fetchAssociative(
            'SELECT rowid FROM llx_societe_remise_except WHERE rowid = ?',
            [$did]
        ));
    }

    public function testRelativeDiscountFlow(): void
    {
        $socid = $this->createThirdparty();
        $server = $this->authJson();

        // note is mandatory (upstream set_remise_client returns -2)
        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/relativediscounts",
            server: $server,
            content: json_encode(['remise' => 10]) ?: ''
        );
        self::assertResponseStatusCodeSame(400);

        $this->client->request(
            'POST',
            "/api/thirdparties/{$socid}/relativediscounts",
            server: $server,
            content: json_encode(['remise' => 10, 'note' => 'vip customer']) ?: ''
        );
        self::assertResponseStatusCodeSame(201);

        // llx_societe.remise_client updated + history row written
        $this->em()->clear();
        self::assertEquals(10.0, $this->em()->find(Societe::class, $socid)?->getRemiseClient());

        $this->client->request('GET', "/api/thirdparties/{$socid}/relativediscounts", server: $server);
        $body = $this->responseBody();
        self::assertEquals(10.0, $body['remise_percent']);
        self::assertCount(1, $body['history']);
        self::assertSame('vip customer', $body['history'][0]['note']);
    }

    public function testRequiresApiKey(): void
    {
        $this->client->request('GET', '/api/thirdparties/1/fixedamountdiscounts');

        self::assertResponseStatusCodeSame(401);
    }
}
