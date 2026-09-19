<?php

declare(strict_types=1);

namespace App\Tests\ThirdParty;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract tests for the third-party API slice (port of
 * htdocs/societe/class/api_thirdparties.class.php endpoints).
 * They exercise the HTTP surface end-to-end against the llx_* tables.
 */
final class ThirdPartyApiTest extends WebTestCase
{
    private const API = '/api/thirdparties';

    private static Connection $db;

    public static function setUpBeforeClass(): void
    {
        $client = self::createClient(); // boots the kernel
        self::$db = self::getContainer()->get('doctrine.dbal.default_connection');
        self::ensureKernelShutdown();

        // clean slate for the fixtures this class owns
        foreach (['llx_societe_commerciaux', 'llx_societe_rib', 'llx_societe_account',
                     'llx_societe_remise_except', 'llx_notify_def', 'llx_categorie_societe',
                     'llx_categorie_fournisseur', 'llx_categorie', 'llx_societe_prices',
                     'llx_societe', 'llx_user'] as $table) {
            try {
                self::$db->executeStatement("DELETE FROM $table");
            } catch (\Throwable) {
            }
        }
    }

    private static function client(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $keys = explode(',', (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? 'dolibarr-dev-key'));
        $client->setServerParameter('HTTP_DOLAPIKEY', trim($keys[0]));

        return $client;
    }

    /** @return array<string, mixed> */
    private static function json(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }

    // ------------------------------------------------------------------

    public function testCreateRequiresName(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode([]));

        self::assertResponseStatusCodeSame(400);
        $body = self::json($client);
        self::assertSame(400, $body['error']['code']);
        self::assertSame('name field missing', $body['error']['message']);
    }

    public function testCreateFetchUpdateDeleteRoundtrip(): void
    {
        $client = self::client();

        // POST — returns the new id (int), like upstream
        $client->request('POST', self::API, content: json_encode([
            'name' => 'Acme Corp',
            'client' => 1,
            'email' => 'contact@acme.test',
            'barcode' => 'BC-ACME-1',
        ]));
        self::assertResponseIsSuccessful();
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);
        self::assertGreaterThan(0, $id);

        // GET by id — upstream-shaped object
        $client->request('GET', self::API.'/'.$id);
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('Acme Corp', $body['name']);
        self::assertSame('contact@acme.test', $body['email']);
        self::assertSame(1, (int) $body['client']);
        // keys removed by _cleanObjectDatas must not be present
        self::assertArrayNotHasKey('nom', $body);
        self::assertArrayNotHasKey('db', $body);
        self::assertArrayNotHasKey('errors', $body);

        // GET by email / barcode
        $client->request('GET', self::API.'/email/contact@acme.test');
        self::assertResponseIsSuccessful();
        self::assertSame($id, (int) self::json($client)['id']);

        $client->request('GET', self::API.'/barcode/BC-ACME-1');
        self::assertResponseIsSuccessful();
        self::assertSame($id, (int) self::json($client)['id']);

        // PUT — returns the updated object
        $client->request('PUT', self::API.'/'.$id, content: json_encode([
            'name' => 'Acme Corporation',
            'town' => 'Springfield',
        ]));
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('Acme Corporation', $body['name']);
        self::assertSame('Springfield', $body['town']);

        // DELETE — upstream success envelope
        $client->request('DELETE', self::API.'/'.$id);
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame(200, $body['success']['code']);
        self::assertSame('Object deleted', $body['success']['message']);

        $client->request('GET', self::API.'/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetMissingReturns404(): void
    {
        $client = self::client();
        $client->request('GET', self::API.'/999999');

        self::assertResponseStatusCodeSame(404);
        $body = self::json($client);
        self::assertSame('Thirdparty not found', $body['error']['message']);
    }

    public function testIndexListAndFilters(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'ListMe Alpha', 'client' => 1]));
        self::assertResponseIsSuccessful();
        $client->request('POST', self::API, content: json_encode(['name' => 'ListMe Beta', 'client' => 1]));
        self::assertResponseIsSuccessful();

        // index with mode=1 (customers)
        $client->request('GET', self::API.'?mode=1&sortfield=t.nom&sortorder=ASC');
        self::assertResponseIsSuccessful();
        $rows = self::json($client);
        self::assertGreaterThanOrEqual(2, count($rows));
        self::assertSame('ListMe Alpha', $rows[0]['name']);

        // sqlfilters on name
        $client->request('GET', self::API."?mode=1&sqlfilters=(t.nom:like:'ListMe Beta')");
        self::assertResponseIsSuccessful();
        $rows = self::json($client);
        self::assertSame('ListMe Beta', $rows[0]['name']);

        // properties filter keeps only requested fields
        $client->request('GET', self::API.'?mode=1&properties=id,name');
        self::assertResponseIsSuccessful();
        $rows = self::json($client);
        self::assertSame(['id', 'name'], array_keys($rows[0]));

        // bad sqlfilter syntax → 400
        $client->request('GET', self::API."?sqlfilters=(t.nom:badop:'x')");
        self::assertResponseStatusCodeSame(400);

        // pagination_data wraps results
        $client->request('GET', self::API.'?mode=1&pagination_data=1&limit=1');
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('pagination', $body);
        self::assertSame(1, $body['pagination']['limit']);

        // empty result for a mode with no rows → 404 with mode message
        $client->request('GET', self::API.'?mode=4');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('No suppliers found', self::json($client)['error']['message']);
    }

    public function testMerge(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'MergeTarget', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);
        $client->request('POST', self::API, content: json_encode(['name' => 'MergeOrigin', 'client' => 1]));
        $origin = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('PUT', self::API.'/'.$id.'/merge/'.$origin);
        self::assertResponseIsSuccessful();
        self::assertSame('MergeTarget', self::json($client)['name']);

        $client->request('GET', self::API.'/'.$origin);
        self::assertResponseStatusCodeSame(404);
    }

    public function testMergeIntoItselfIs400(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'SelfMerge', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('PUT', self::API.'/'.$id.'/merge/'.$id);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('Try to merge a thirdparty into itself', self::json($client)['error']['message']);
    }

    public function testRepresentatives(): void
    {
        $userId = (int) self::$db->insert('llx_user', [
            'login' => 'rep1', 'firstname' => 'Rep', 'lastname' => 'One', 'entity' => 1, 'statut' => 1,
        ]) ? (int) self::$db->lastInsertId() : 0;
        self::assertGreaterThan(0, $userId);

        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'RepCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('POST', self::API.'/'.$id.'/representative/'.$userId);
        self::assertResponseIsSuccessful();

        $client->request('GET', self::API.'/'.$id.'/representatives');
        self::assertResponseIsSuccessful();
        $reps = self::json($client);
        self::assertCount(1, $reps);
        self::assertSame('One', $reps[0]['lastname']);

        $client->request('GET', self::API.'/'.$id.'/representatives?mode=1');
        self::assertResponseIsSuccessful();
        self::assertSame([$userId], self::json($client));

        $client->request('DELETE', self::API.'/'.$id.'/representative/'.$userId);
        self::assertResponseIsSuccessful();

        $client->request('GET', self::API.'/'.$id.'/representatives?mode=1');
        self::assertSame([], self::json($client));
    }

    public function testCategories(): void
    {
        self::$db->insert('llx_categorie', [
            'entity' => 1, 'label' => 'CustCat', 'type' => 2, 'visible' => 1, 'position' => 0,
        ]);
        $catId = (int) self::$db->lastInsertId();

        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'CatCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('PUT', self::API.'/'.$id.'/categories/'.$catId);
        self::assertResponseIsSuccessful();
        self::assertSame('CatCorp', self::json($client)['name']);

        $client->request('GET', self::API.'/'.$id.'/categories');
        self::assertResponseIsSuccessful();
        $cats = self::json($client);
        self::assertCount(1, $cats);
        self::assertSame('CustCat', $cats[0]['label']);

        $client->request('DELETE', self::API.'/'.$id.'/categories/'.$catId);
        self::assertResponseIsSuccessful();

        $client->request('GET', self::API.'/'.$id.'/categories');
        self::assertSame([], self::json($client));
    }

    public function testBankAccounts(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'BankCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        // create
        $client->request('POST', self::API.'/'.$id.'/bankaccounts', content: json_encode([
            'bank' => 'TestBank', 'iban' => 'FR7630006000011234567890189', 'bic' => 'AGRIFRPP',
        ]));
        self::assertResponseIsSuccessful();
        $account = self::json($client);
        self::assertSame('TestBank', $account['bank']);
        self::assertNotEmpty($account['rum']); // auto-built RUM number

        // list
        $client->request('GET', self::API.'/'.$id.'/bankaccounts');
        self::assertResponseIsSuccessful();
        $accounts = self::json($client);
        self::assertCount(1, $accounts);
        self::assertSame('TestBank', $accounts[0]['bank']);
        self::assertSame('FR7630006000011234567890189', $accounts[0]['iban']); // decrypted

        // update
        $accId = (int) $accounts[0]['id'];
        $client->request('PUT', self::API.'/'.$id.'/bankaccounts/'.$accId, content: json_encode(['bank' => 'OtherBank']));
        self::assertResponseIsSuccessful();
        self::assertSame('OtherBank', self::json($client)['bank']);

        // delete
        $client->request('DELETE', self::API.'/'.$id.'/bankaccounts/'.$accId);
        self::assertResponseIsSuccessful();
        $client->request('GET', self::API.'/'.$id.'/bankaccounts');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSocieteAccounts(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'AcctCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('POST', self::API.'/'.$id.'/accounts', content: json_encode([
            'site' => 'stripe', 'key_account' => 'cus_123',
        ]));
        self::assertResponseIsSuccessful();
        self::assertSame('cus_123', self::json($client)['key_account']);

        // duplicate site → 409
        $client->request('POST', self::API.'/'.$id.'/accounts', content: json_encode([
            'site' => 'stripe', 'key_account' => 'cus_456',
        ]));
        self::assertResponseStatusCodeSame(409);

        // list
        $client->request('GET', self::API.'/'.$id.'/accounts');
        self::assertResponseIsSuccessful();
        self::assertCount(1, self::json($client));

        // lookup by site + key
        $client->request('GET', self::API.'/accounts/stripe/cus_123');
        self::assertResponseIsSuccessful();
        self::assertSame('AcctCorp', self::json($client)['name']);

        // delete
        $client->request('DELETE', self::API.'/'.$id.'/accounts/stripe');
        self::assertResponseStatusCodeSame(200);
    }

    public function testNotifications(): void
    {
        self::$db->insert('llx_c_action_trigger', [
            'elementtype' => 'societe', 'code' => 'COMPANY_CREATE', 'label' => 'Company created',
        ]);
        $actionId = (int) self::$db->lastInsertId();

        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'NotifCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        // create by event id
        $client->request('POST', self::API.'/'.$id.'/notifications', content: json_encode([
            'event' => $actionId, 'contact_id' => 7,
        ]));
        self::assertResponseIsSuccessful();
        $notif = self::json($client);
        self::assertSame($actionId, (int) $notif['event']);

        // duplicate → 403
        $client->request('POST', self::API.'/'.$id.'/notifications', content: json_encode([
            'event' => $actionId, 'contact_id' => 7,
        ]));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Notification already exists', self::json($client)['error']['message']);

        // create by code
        $client->request('POST', self::API.'/'.$id.'/notificationsbycode/COMPANY_CREATE', content: json_encode(['contact_id' => 8]));
        self::assertResponseIsSuccessful();

        // unknown code → 404
        $client->request('POST', self::API.'/'.$id.'/notificationsbycode/NOPE', content: json_encode(['contact_id' => 8]));
        self::assertResponseStatusCodeSame(404);

        // list
        $client->request('GET', self::API.'/'.$id.'/notifications');
        self::assertResponseIsSuccessful();
        self::assertCount(2, self::json($client));
    }

    public function testFixedAmountDiscountsAndSplit(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'DiscCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        // create customer discount (HT)
        $client->request('POST', self::API.'/'.$id.'/fixedamountdiscounts', content: json_encode([
            'amount' => 100, 'description' => 'Gift', 'tva_tx' => 20,
        ]));
        self::assertResponseIsSuccessful();
        $discountId = (int) json_decode((string) $client->getResponse()->getContent(), true);
        self::assertGreaterThan(0, $discountId);

        // list
        $client->request('GET', self::API.'/'.$id.'/fixedamountdiscounts');
        self::assertResponseIsSuccessful();
        $rows = self::json($client);
        self::assertCount(1, $rows);
        self::assertSame('Gift', $rows[0]['description']);
        self::assertArrayHasKey('ref', $rows[0]);       // invoice soft ref
        self::assertNull($rows[0]['ref']);              // not resolvable in this domain
        self::assertSame(100.0, (float) $rows[0]['amount_ht']);

        // absolute_discount sums amount_ttc (100 HT @20% = 120) like upstream
        $client->request('GET', self::API.'/'.$id);
        self::assertSame(120.0, (float) self::json($client)['absolute_discount']);

        // split (sum must match original amount_ttc = 100*1.2 = 120)
        $client->request('POST', self::API.'/'.$id.'/splitdiscount/'.$discountId,
            content: json_encode(['amount_ttc_1' => 50, 'amount_ttc_2' => 70]));
        self::assertResponseIsSuccessful();
        self::assertCount(2, self::json($client));

        // mismatched split → 405
        $client->request('POST', self::API.'/'.$id.'/fixedamountdiscounts', content: json_encode([
            'amount' => 10, 'description' => 'Gift2',
        ]));
        $d2 = (int) json_decode((string) $client->getResponse()->getContent(), true);
        $client->request('POST', self::API.'/'.$id.'/splitdiscount/'.$d2,
            content: json_encode(['amount_ttc_1' => 5, 'amount_ttc_2' => 99]));
        self::assertResponseStatusCodeSame(405);
    }

    public function testOutstandingEndpoints(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'OutCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        foreach (['outstandingproposals', 'outstandingorders', 'outstandinginvoices'] as $endpoint) {
            $client->request('GET', self::API.'/'.$id.'/'.$endpoint);
            self::assertResponseIsSuccessful();
            $body = self::json($client);
            self::assertSame(0, $body['opened']);
            self::assertArrayHasKey('refs', $body);
        }
    }

    public function testSetPriceLevelGate(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['name' => 'PriceCorp', 'client' => 1]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        // product module / multiprices not enabled → 501 like upstream
        $client->request('PUT', self::API.'/'.$id.'/setpricelevel/2');
        self::assertResponseStatusCodeSame(501);
    }
}
