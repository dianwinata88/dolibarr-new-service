<?php

declare(strict_types=1);

namespace App\Tests\BankAccount;

use App\Entity\Societe;
use App\Entity\SocieteRib;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the third-party bank account endpoints, mirroring
 * upstream api_thirdparties.class.php behaviors:
 *
 *  - GET    /api/thirdparties/{id}/bankaccounts           (404 when empty)
 *  - POST   /api/thirdparties/{id}/bankaccounts           (200, default_rib
 *    fixup, auto-generated RUM, per-field 'extrafields' sanitization)
 *  - PUT    /api/thirdparties/{id}/bankaccounts/{rib}     (403 on socid
 *    mismatch or missing rib, 404 on missing company)
 *  - DELETE /api/thirdparties/{id}/bankaccounts/{rib}     (body `1`, 403 on
 *    mismatch/missing rib, no company existence check)
 */
final class BankAccountApiTest extends WebTestCase
{
    private static bool $dbReady = false;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    public static function setUpBeforeClass(): void
    {
        // The test database dolibarr_crm_test is created via the root account
        // (the app user only owns dolibarr_crm), then schema is synced.
        try {
            $pdo = new \PDO('mysql:host=database;port=3306', 'root', 'root', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS dolibarr_crm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            );
            $pdo->exec("GRANT ALL PRIVILEGES ON `dolibarr_crm_test`.* TO 'dolibarr'@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
            self::$dbReady = true;
        } catch (\Throwable $e) {
            self::markTestSkipped('Test database unreachable: ' . $e->getMessage());
        }

        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        if (!self::$dbReady) {
            self::markTestSkipped('Test database unreachable');
        }
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $this->em = $em;
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['llx_societe_rib', 'llx_societe'] as $table) {
            $this->em->getConnection()->executeStatement("TRUNCATE TABLE `{$table}`");
        }
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function apiKey(): string
    {
        return trim(explode(',', (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? 'test-api-key'))[0]);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function request(string $method, string $uri, ?array $body = null, array $server = []): void
    {
        $server = array_merge([
            'HTTP_DOLAPIKEY' => $this->apiKey(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $server);
        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: $body === null ? null : (string) json_encode($body),
        );
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>|int|string
     */
    private function jsonResponse(): array|int|string
    {
        $content = (string) $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true);

        return $decoded === null ? $content : $decoded;
    }

    private function createCompany(string $name = 'Acme Corp', ?string $codeClient = 'CUST-01'): int
    {
        $company = new Societe();
        $company->setNom($name);
        $company->setCodeClient($codeClient);
        $this->em->persist($company);
        $this->em->flush();

        return (int) $company->getRowid();
    }

    public function testListReturns404WhenNoAccount(): void
    {
        $this->request('GET', '/api/thirdparties/1/bankaccounts');
        self::assertResponseStatusCodeSame(404);
        $body = $this->jsonResponse();
        self::assertIsArray($body);
        self::assertSame('Account not found', $body['detail'] ?? null);
    }

    public function testListReturns400OnZeroId(): void
    {
        $this->request('GET', '/api/thirdparties/0/bankaccounts');
        self::assertResponseStatusCodeSame(400);
    }

    public function testRequiresApiKey(): void
    {
        $this->client->request('GET', '/api/thirdparties/1/bankaccounts');
        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateListUpdateDeleteFlow(): void
    {
        $socId = $this->createCompany();

        // POST creates a rib: label gets sanitized, rum auto-generated,
        // first 'ban' account becomes default.
        $this->request('POST', "/api/thirdparties/{$socId}/bankaccounts", [
            'label' => '<b>Main</b> IBAN',
            'bank' => 'BNP',
            'code_banque' => '30001',
            'code_guichet' => '00794',
            'number' => '12345678901',
            'cle_rib' => '43',
            'bic' => 'BNPAFRPPXXX',
            'iban' => 'FR7630001007941234567890185',
            'owner_name' => 'Acme Corp',
            'address' => 'Paris HQ',
            'frstrecur' => 'RCUR',
        ]);
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $created */
        $created = $this->jsonResponse();
        self::assertIsArray($created);
        self::assertSame($socId, $created['socid']);
        self::assertSame(1, $created['default_rib']);
        self::assertSame('Main IBAN', $created['label']); // tags stripped by sanitizer
        self::assertSame('FR7630001007941234567890185', $created['iban']);
        self::assertSame('BNP', $created['bank']);
        self::assertMatchesRegularExpression('/^RUM-\d{10}-\d+-CUST-01$/', (string) $created['rum']);
        self::assertIsInt($created['date_rum']);
        self::assertSame('ban', $created['type']);
        self::assertArrayNotHasKey('ref', $created); // ref only exists after fetch
        $ribId = (int) $created['id'];

        // Request 'email' is an inert prop upstream: accepted, never persisted.
        $rib = $this->em->find(SocieteRib::class, $ribId);
        self::assertInstanceOf(SocieteRib::class, $rib);
        self::assertNull($rib->getEmail());
        self::assertSame(0, $rib->getStatus()); // upstream writes (int)null -> 0

        // Second account requesting default_rib is demoted to 0 (fixup).
        $this->request('POST', "/api/thirdparties/{$socId}/bankaccounts", [
            'label' => 'Second', 'iban' => 'DE89370400440532013000', 'default_rib' => 1,
        ]);
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $second */
        $second = $this->jsonResponse();
        self::assertSame(0, $second['default_rib']);

        // GET collection: upstream returns only the whitelisted keys.
        $this->request('GET', "/api/thirdparties/{$socId}/bankaccounts");
        self::assertResponseStatusCodeSame(200);
        /** @var array<int, array<string, mixed>> $list */
        $list = $this->jsonResponse();
        self::assertIsArray($list);
        self::assertCount(2, $list);
        $allowed = ['socid', 'default_rib', 'frstrecur', 'datec', 'datem', 'label', 'bank', 'bic', 'iban', 'id', 'rum'];
        foreach ($list as $entry) {
            self::assertEmpty(array_diff(array_keys($entry), $allowed), 'list entries only carry upstream keys');
        }
        self::assertSame('FR7630001007941234567890185', $list[0]['iban']);
        self::assertSame(1, $list[0]['default_rib']);

        // PUT updates writable props; response carries fetch() extras.
        $this->request('PUT', "/api/thirdparties/{$socId}/bankaccounts/{$ribId}", [
            'label' => 'Renamed', 'state_id' => 5, 'status' => 1, 'email' => 'x@y.z',
        ]);
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $updated */
        $updated = $this->jsonResponse();
        self::assertSame("{$socId}-Renamed", $updated['ref']);
        self::assertSame('Renamed', $updated['label']);
        self::assertSame(5, $updated['state_id']);
        self::assertSame(1, $updated['status']);
        // inert request fields are still dropped
        self::assertNull($this->em->find(SocieteRib::class, $ribId)?->getEmail());

        // DELETE returns the raw int 1 like upstream.
        $this->request('DELETE', "/api/thirdparties/{$socId}/bankaccounts/{$ribId}");
        self::assertResponseStatusCodeSame(200);
        self::assertSame('1', (string) $this->client->getResponse()->getContent());

        // After deleting one account, the list still shows the other.
        $this->request('GET', "/api/thirdparties/{$socId}/bankaccounts");
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, (array) $this->jsonResponse());
    }

    public function testCreateOnMissingCompanyReturns404(): void
    {
        $this->request('POST', '/api/thirdparties/4242/bankaccounts', ['label' => 'x']);
        self::assertResponseStatusCodeSame(404);
        $body = $this->jsonResponse();
        self::assertIsArray($body);
        self::assertSame("Error creating Company Bank account, Company doesn't exists", $body['detail'] ?? null);
    }

    public function testUpdateWrongSocidReturns403(): void
    {
        $socId = $this->createCompany();
        $otherSocId = $this->createCompany('Other Corp', 'CUST-02');

        $this->request('POST', "/api/thirdparties/{$socId}/bankaccounts", ['label' => 'X']);
        /** @var array<string, mixed> $created */
        $created = $this->jsonResponse();
        $ribId = (int) $created['id'];

        $this->request('PUT', "/api/thirdparties/{$otherSocId}/bankaccounts/{$ribId}", ['label' => 'Y']);
        self::assertResponseStatusCodeSame(403);

        $this->request('PUT', "/api/thirdparties/{$socId}/bankaccounts/99999", ['label' => 'Y']);
        self::assertResponseStatusCodeSame(403); // missing rib -> socid mismatch upstream
    }

    public function testUpdateOnMissingCompanyReturns404(): void
    {
        $this->request('PUT', '/api/thirdparties/4242/bankaccounts/1', ['label' => 'Y']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteSemantics(): void
    {
        $socId = $this->createCompany();
        $otherSocId = $this->createCompany('Other Corp', 'CUST-02');

        $this->request('POST', "/api/thirdparties/{$socId}/bankaccounts", ['label' => 'X']);
        /** @var array<string, mixed> $created */
        $created = $this->jsonResponse();
        $ribId = (int) $created['id'];

        // wrong third party -> 403 'Not allowed due to bad consistency'
        $this->request('DELETE', "/api/thirdparties/{$otherSocId}/bankaccounts/{$ribId}");
        self::assertResponseStatusCodeSame(403);
        $body = $this->jsonResponse();
        self::assertIsArray($body);
        self::assertSame('Not allowed due to bad consistency of input data', $body['detail'] ?? null);

        // nonexistent rib -> same 403 (upstream maps fetch-failure to socid 0)
        $this->request('DELETE', "/api/thirdparties/{$socId}/bankaccounts/99999");
        self::assertResponseStatusCodeSame(403);

        // missing company still allows delete (upstream does not fetch company)
        $this->request('DELETE', "/api/thirdparties/4242/bankaccounts/{$ribId}");
        self::assertResponseStatusCodeSame(403); // socid mismatch, not 404
    }

    public function testForbiddenFieldOnPutReturns400(): void
    {
        $socId = $this->createCompany();
        $this->request('POST', "/api/thirdparties/{$socId}/bankaccounts", ['label' => 'X']);
        /** @var array<string, mixed> $created */
        $created = $this->jsonResponse();

        $this->request('PUT', "/api/thirdparties/{$socId}/bankaccounts/{$created['id']}", ['db' => 'x']);
        self::assertResponseStatusCodeSame(400);
    }

    public function testIbanIsStoredEncryptedWhenKeyConfigured(): void
    {
        $socId = $this->createCompany();
        $this->request('POST', "/api/thirdparties/{$socId}/bankaccounts", ['iban' => 'FR7630001007941234567890185']);
        /** @var array<string, mixed> $created */
        $created = $this->jsonResponse();

        $rib = $this->em->find(SocieteRib::class, (int) $created['id']);
        self::assertInstanceOf(SocieteRib::class, $rib);
        $stored = (string) $rib->getIbanPrefix();
        $key = (string) ($_SERVER['DOLIBARR_INSTANCE_UNIQUE_ID'] ?? '');
        if ($key !== '') {
            self::assertStringStartsWith('dolcrypt:AES-256-CTR:', $stored);
        }
        // the API always returns the decrypted value
        self::assertSame('FR7630001007941234567890185', $created['iban']);
    }
}
