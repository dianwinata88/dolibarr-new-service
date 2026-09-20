<?php

declare(strict_types=1);

namespace App\Tests\SalesRep;

use App\Tests\Support\TestApiKeys;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contract tests for the thirdparty site-account endpoints — port of the
 * {id}/accounts routes of upstream api_thirdparties.class.php backed by
 * llx_societe_account.
 */
final class SocieteAccountApiTest extends WebTestCase
{
    private const API = '/api/thirdparties';

    private static Connection $db;

    public static function setUpBeforeClass(): void
    {
        self::createClient();
        self::$db = self::getContainer()->get('doctrine.dbal.default_connection');
        self::ensureKernelShutdown();

        foreach (
            ['llx_societe_account', 'llx_societe_commerciaux', 'llx_societe_log',
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

    private static function json(KernelBrowser $client): mixed
    {
        return json_decode((string) $client->getResponse()->getContent(), true);
    }

    private static function insertSociete(string $name, int $entity = 1): int
    {
        self::$db->insert('llx_societe', ['nom' => $name, 'entity' => $entity, 'fk_stcomm' => 0]);

        return (int) self::$db->lastInsertId();
    }

    // ------------------------------------------------------------------

    public function testGetAccountsEmptyReturns404(): void
    {
        $client = self::client();
        $socid = self::insertSociete('NoAccounts');

        $client->request('GET', self::API . "/$socid/accounts");
        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            'This thirdparty does not have any account attached or does not exist.',
            self::json($client)['error']['message'],
        );
    }

    public function testCreateAccountValidationAndConflict(): void
    {
        $client = self::client();
        $socid = self::insertSociete('AcctCorp');

        // missing site -> 422 verbatim upstream message
        $client->request('POST', self::API . "/$socid/accounts", content: json_encode(['key_account' => 'cus_1']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            'Unprocessable Entity: You must pass the site attribute in your request data !',
            self::json($client)['error']['message'],
        );

        // create
        $client->request('POST', self::API . "/$socid/accounts", content: json_encode([
            'site' => 'stripe',
            'key_account' => 'cus_ABC123',
            'caller' => 'external-sync', // dropped — context only, never a column
        ]));
        self::assertResponseIsSuccessful();
        $account = self::json($client);
        self::assertSame('stripe', $account['site']);
        self::assertSame('cus_ABC123', $account['key_account']);
        self::assertSame('', $account['login']); // upstream default when unset
        self::assertSame($socid, (int) $account['fk_soc']);
        self::assertArrayNotHasKey('caller', $account);
        $rowid = (int) $account['id'];
        self::assertGreaterThan(0, $rowid);

        // duplicate (fk_soc, site) -> 409
        $client->request('POST', self::API . "/$socid/accounts", content: json_encode([
            'site' => 'stripe',
            'key_account' => 'cus_OTHER',
        ]));
        self::assertResponseStatusCodeSame(409);
        self::assertSame(
            'A SocieteAccount entity already exists for this company and site.',
            self::json($client)['error']['message'],
        );

        // GET list — filtered to the upstream field list
        $client->request('GET', self::API . "/$socid/accounts");
        self::assertResponseIsSuccessful();
        $list = self::json($client);
        self::assertCount(1, $list);
        $keys = array_keys($list[0]);
        sort($keys);
        self::assertSame(['date_creation', 'fk_soc', 'id', 'key_account', 'site', 'tms'], $keys);

        // GET list filtered by site
        $client->request('GET', self::API . "/$socid/accounts", parameters: ['site' => 'nostripe']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetThirdpartyByAccount(): void
    {
        $client = self::client();
        $socid = self::insertSociete('LookupCorp');
        self::$db->insert('llx_societe_account', [
            'entity' => 1, 'login' => '', 'fk_soc' => $socid, 'site' => 'stripe',
            'key_account' => 'cus_LOOKUP', 'date_creation' => date('Y-m-d H:i:s'),
            'fk_user_creat' => 0, 'status' => 1,
        ]);

        $client->request('GET', self::API . '/accounts/stripe/cus_LOOKUP');
        self::assertResponseIsSuccessful();
        $thirdparty = self::json($client);
        self::assertSame($socid, (int) $thirdparty['id']);
        // upstream _cleanObjectDatas unsets 'nom' (deprecated alias of 'name')
        self::assertSame('LookupCorp', $thirdparty['name']);

        // no unique match -> 404 verbatim
        $client->request('GET', self::API . '/accounts/stripe/cus_NOPE');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            'This account have many thirdparties attached or does not exist.',
            self::json($client)['error']['message'],
        );
    }

    public function testPostAccountBySiteCreateOrReplace(): void
    {
        $client = self::client();
        $socid = self::insertSociete('SiteCorp');

        // create path requires key_account -> 422
        $client->request('POST', self::API . "/$socid/accounts/stripe", content: json_encode(['login' => 'u1']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            'Unprocessable Entity: You must pass the key_account attribute in your request data !',
            self::json($client)['error']['message'],
        );

        // create path — body fk_soc/site are ignored, URL wins
        $client->request('POST', self::API . "/$socid/accounts/stripe", content: json_encode([
            'key_account' => 'cus_SITE',
            'site' => 'other',
            'fk_soc' => 999999,
        ]));
        self::assertResponseIsSuccessful();
        $account = self::json($client);
        self::assertSame('stripe', $account['site']);
        self::assertSame($socid, (int) $account['fk_soc']);
        $firstRowid = (int) $account['id'];
        $firstCreation = $account['date_creation'];

        // replace path — unpassed fields reset, creator/creation preserved
        $client->request('POST', self::API . "/$socid/accounts/stripe", content: json_encode([
            'key_account' => 'cus_SITE2',
        ]));
        self::assertResponseIsSuccessful();
        $account = self::json($client);
        self::assertSame($firstRowid, (int) $account['id']);
        self::assertSame('cus_SITE2', $account['key_account']);
        self::assertSame($firstCreation, $account['date_creation']);

        // site rename collision -> 409
        self::$db->insert('llx_societe_account', [
            'entity' => 1, 'login' => '', 'fk_soc' => $socid, 'site' => 'other',
            'key_account' => 'cus_OTHER', 'date_creation' => date('Y-m-d H:i:s'),
            'fk_user_creat' => 0, 'status' => 1,
        ]);
        $client->request('POST', self::API . "/$socid/accounts/stripe", content: json_encode([
            'site' => 'other',
        ]));
        self::assertResponseStatusCodeSame(409);
        self::assertSame(
            'You are trying to update this thirdparty Account for stripe to other'
                . ' but another Account already exists with this site key.',
            self::json($client)['error']['message'],
        );
    }

    public function testPutAccountBySite(): void
    {
        $client = self::client();
        $socid = self::insertSociete('PutCorp');

        $client->request('PUT', self::API . "/$socid/accounts/stripe", content: json_encode(['key_account' => 'x']));
        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            'This thirdparty does not have stripe account attached or does not exist.',
            self::json($client)['error']['message'],
        );

        self::$db->insert('llx_societe_account', [
            'entity' => 1, 'login' => 'orig', 'fk_soc' => $socid, 'site' => 'stripe',
            'key_account' => 'cus_OLD', 'date_creation' => '2020-01-01 00:00:00',
            'fk_user_creat' => 7, 'status' => 1,
        ]);

        // PUT keeps unpassed fields (fetch + update), unlike POST replace
        $client->request('PUT', self::API . "/$socid/accounts/stripe", content: json_encode([
            'key_account' => 'cus_NEW',
        ]));
        self::assertResponseIsSuccessful();
        $account = self::json($client);
        self::assertSame('cus_NEW', $account['key_account']);
        self::assertSame('orig', $account['login']);
        self::assertSame(7, (int) $account['fk_user_creat']);
        self::assertSame('2020-01-01 00:00:00', $account['date_creation']);
    }

    public function testDeleteAccounts(): void
    {
        $client = self::client();
        $socid = self::insertSociete('DelCorp');

        $client->request('DELETE', self::API . "/$socid/accounts/stripe");
        self::assertResponseStatusCodeSame(404);

        $client->request('DELETE', self::API . "/$socid/accounts");
        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            'This third party does not have any account attached or does not exist.',
            self::json($client)['error']['message'],
        );

        foreach (['a', 'b'] as $site) {
            self::$db->insert('llx_societe_account', [
                'entity' => 1, 'login' => '', 'fk_soc' => $socid, 'site' => $site,
                'key_account' => "k_$site", 'date_creation' => date('Y-m-d H:i:s'),
                'fk_user_creat' => 0, 'status' => 1,
            ]);
        }

        $client->request('DELETE', self::API . "/$socid/accounts/a");
        self::assertResponseIsSuccessful();
        self::assertSame(1, (int) self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_societe_account WHERE fk_soc = ?',
            [$socid],
        ));

        $client->request('DELETE', self::API . "/$socid/accounts");
        self::assertResponseIsSuccessful();
        self::assertSame(0, (int) self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_societe_account WHERE fk_soc = ?',
            [$socid],
        ));
    }
}
