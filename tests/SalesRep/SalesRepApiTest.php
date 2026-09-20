<?php

declare(strict_types=1);

namespace App\Tests\SalesRep;

use App\Tests\Support\TestApiKeys;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contract tests for the representative assignment endpoints — port of the
 * {id}/representative(s) routes of upstream api_thirdparties.class.php and
 * Societe::add_commercial()/del_commercial()/getSalesRepresentatives().
 */
final class SalesRepApiTest extends WebTestCase
{
    private const API = '/api/thirdparties';

    private static Connection $db;

    public static function setUpBeforeClass(): void
    {
        self::createClient(); // boots the kernel
        self::$db = self::getContainer()->get('doctrine.dbal.default_connection');
        self::ensureKernelShutdown();

        foreach (
            ['llx_societe_commerciaux', 'llx_societe_account', 'llx_societe_log',
                     'llx_societe_perentity', 'llx_societe', 'llx_user'] as $table
        ) {
            try {
                self::$db->executeStatement("DELETE FROM $table");
            } catch (\Throwable) {
            }
        }
    }

    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $client->setServerParameter('HTTP_DOLAPIKEY', TestApiKeys::first());

        return $client;
    }

    /** @return array<mixed>|int|string|null */
    private static function json(KernelBrowser $client): mixed
    {
        return json_decode((string) $client->getResponse()->getContent(), true);
    }

    private static function insertSociete(string $name, int $entity = 1): int
    {
        self::$db->insert('llx_societe', ['nom' => $name, 'entity' => $entity, 'fk_stcomm' => 0]);

        return (int) self::$db->lastInsertId();
    }

    private static function insertUser(string $login, int $entity = 1): int
    {
        self::$db->insert('llx_user', [
            'login' => $login,
            'firstname' => 'John',
            'lastname' => 'Doe',
            'entity' => $entity,
            'email' => $login . '@test.local',
            'job' => 'Sales',
            'office_phone' => '0102030405',
            'statut' => 1,
        ]);

        return (int) self::$db->lastInsertId();
    }

    // ------------------------------------------------------------------

    public function testGetRepresentativeOnMissingThirdparty(): void
    {
        $client = self::client();
        $client->request('GET', self::API . '/999999/representative');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Thirdparty not found', self::json($client)['error']['message']);
    }

    public function testAssignListUnassignRoundtrip(): void
    {
        $client = self::client();
        $socid = self::insertSociete('RepCorp');
        $userId = self::insertUser('rep1');
        $otherEntityUser = self::insertUser('rep_entity0', 0);

        // assign — upstream returns the add_commercial() result (int 1)
        $client->request('POST', self::API . "/$socid/representative/$userId");
        self::assertResponseIsSuccessful();
        self::assertSame(1, self::json($client));

        // re-assign same pair — upstream delete+insert is idempotent
        $client->request('POST', self::API . "/$socid/representative/$userId");
        self::assertResponseIsSuccessful();
        self::assertSame(1, self::json($client));

        // entity 0 user is visible (shared entity)
        $client->request('POST', self::API . "/$socid/representative/$otherEntityUser");
        self::assertResponseIsSuccessful();

        // GET {id}/representative — upstream-shaped rep rows
        $client->request('GET', self::API . "/$socid/representative");
        self::assertResponseIsSuccessful();
        $reps = self::json($client);
        self::assertCount(2, $reps);
        $reps = array_values(array_filter($reps, static fn (array $r): bool => $r['login'] === 'rep1'));
        $rep = $reps[0];
        self::assertSame($userId, (int) $rep['id']);
        self::assertSame('Doe', $rep['lastname']);
        self::assertSame('rep1@test.local', $rep['email']);
        self::assertSame('0102030405', $rep['office_phone']);
        self::assertSame('0102030405', $rep['phone']); // upstream aliases office_phone
        self::assertSame('Sales', $rep['job']);
        self::assertArrayHasKey('statut', $rep);
        self::assertArrayHasKey('status', $rep);
        self::assertSame($rep['statut'], $rep['status']);

        // GET {id}/representatives?mode=1 — plain id list
        $client->request('GET', self::API . "/$socid/representatives?mode=1");
        self::assertResponseIsSuccessful();
        $ids = self::json($client);
        sort($ids);
        self::assertSame([$userId, $otherEntityUser], array_map('intval', $ids));

        // unassign — del_commercial returns 1
        $client->request('DELETE', self::API . "/$socid/representative/$userId");
        self::assertResponseIsSuccessful();
        self::assertSame(1, self::json($client));

        // unassign again — upstream still returns 1 (delete is not checked)
        $client->request('DELETE', self::API . "/$socid/representative/$userId");
        self::assertResponseIsSuccessful();
        self::assertSame(1, self::json($client));

        $client->request('GET', self::API . "/$socid/representatives?mode=1");
        self::assertSame([$otherEntityUser], array_map('intval', self::json($client)));
    }

    public function testAssignToMissingUserReturns404(): void
    {
        $client = self::client();
        $socid = self::insertSociete('NoUserCorp');

        $client->request('POST', self::API . "/$socid/representative/999999");
        self::assertResponseStatusCodeSame(404);
        self::assertSame('User not found', self::json($client)['error']['message']);

        $client->request('DELETE', self::API . "/$socid/representative/999999");
        self::assertResponseStatusCodeSame(404);
    }

    public function testRepresentativeListOnMissingThirdparty(): void
    {
        $client = self::client();
        $client->request('GET', self::API . '/999999/representatives');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Thirdparty not found', self::json($client)['error']['message']);
    }

    public function testAccessRestrictionBySocid(): void
    {
        $_SERVER['DOLIBARR_API_SOCID'] = $_ENV['DOLIBARR_API_SOCID'] = '42';
        try {
            $socid = self::insertSociete('RestrictedCorp');
            $client = self::client();
            $client->request('GET', self::API . "/$socid/representative");

            self::assertResponseStatusCodeSame(403);
            self::assertStringStartsWith('Access not allowed for login', self::json($client)['error']['message']);
        } finally {
            unset($_SERVER['DOLIBARR_API_SOCID'], $_ENV['DOLIBARR_API_SOCID']);
        }
    }

    public function testRequiresApiKey(): void
    {
        $client = self::createClient();
        $socid = self::insertSociete('AuthCorp');
        $client->request('GET', self::API . "/$socid/representative");

        self::assertResponseStatusCodeSame(401);
    }
}
