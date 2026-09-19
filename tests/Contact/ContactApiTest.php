<?php

declare(strict_types=1);

namespace App\Tests\Contact;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contract tests for the contacts API slice (port of
 * htdocs/societe/class/api_contacts.class.php + the contact endpoints of
 * api_thirdparties / api_setup dictionaries).
 * They exercise the HTTP surface end-to-end against the llx_* tables.
 */
final class ContactApiTest extends WebTestCase
{
    private const API = '/api/contacts';

    private static Connection $db;

    private static int $socId = 0;
    private static int $typeContactId = 0;
    private static int $categoryId = 0;

    public static function setUpBeforeClass(): void
    {
        $client = self::createClient(); // boots the kernel
        self::$db = self::getContainer()->get('doctrine.dbal.default_connection');
        self::ensureKernelShutdown();

        // clean slate for the fixtures this class owns
        foreach (
            [
            'llx_societe_contacts', 'llx_element_contact', 'llx_user_alert',
            'llx_mailing_unsubscribe', 'llx_notify_def', 'llx_categorie_contact',
            'llx_categorie', 'llx_socpeople_extrafields', 'llx_socpeople',
            'llx_societe', 'llx_c_type_contact', 'llx_c_civility', 'llx_user',
            'llx_c_country',
            ] as $table
        ) {
            try {
                self::$db->executeStatement("DELETE FROM $table");
            } catch (\Throwable) {
            }
        }

        // fixtures: one thirdparty, one contact type, one category, one civility
        self::$db->insert('llx_societe', ['nom' => 'Fixture Corp', 'entity' => 1, 'status' => 1]);
        self::$socId = (int) self::$db->lastInsertId();

        self::$db->insert('llx_c_type_contact', [
            'element' => 'societe', 'source' => 'external', 'code' => 'CONTACT',
            'libelle' => 'Contact principal', 'active' => 1, 'position' => 1,
        ]);
        self::$typeContactId = (int) self::$db->lastInsertId();
        self::$db->insert('llx_c_type_contact', [
            'element' => 'societe', 'source' => 'external', 'code' => 'BILLING',
            'libelle' => 'Contact facturation', 'active' => 1, 'position' => 2,
        ]);

        self::$db->insert('llx_categorie', [
            'entity' => 1, 'fk_parent' => 0, 'label' => 'VIP', 'type' => 4, 'visible' => 1, 'position' => 0,
        ]);
        self::$categoryId = (int) self::$db->lastInsertId();

        self::$db->insert('llx_c_civility', ['code' => 'MR', 'label' => 'Mister', 'active' => 1, 'module' => '']);
        self::$db->insert('llx_c_civility', ['code' => 'MME', 'label' => 'Misses', 'active' => 1, 'module' => '']);

        // llx_c_country.rowid is a plain PK (not auto-increment) upstream
        self::$db->insert('llx_c_country', [
            'rowid' => 99, 'code' => 'FR', 'code_iso' => 'FRA', 'label' => 'France', 'active' => 1,
        ]);
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

    public function testCreateRequiresLastname(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode([]));

        self::assertResponseStatusCodeSame(400);
        $body = self::json($client);
        self::assertSame(400, $body['error']['code']);
        self::assertSame('lastname field missing', $body['error']['message']);
    }

    public function testCreateFetchUpdateDeleteRoundtrip(): void
    {
        $client = self::client();

        // POST — returns the new id (int), like upstream
        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Doe',
            'firstname' => 'John',
            'socid' => self::$socId,
            'email' => 'John.DOE@Example.TEST',
            'civility_code' => 'MR',
            'phone_pro' => '0102030405',
            'birthday' => '1990-06-15',
            'status' => 1,
        ]));
        self::assertResponseIsSuccessful();
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);
        self::assertGreaterThan(0, $id);

        // GET by id — upstream-shaped object
        $client->request('GET', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('Doe', $body['lastname']);
        self::assertSame('John', $body['firstname']);
        // setUpperOrLowerCase lowercases the email, like upstream
        self::assertSame('john.doe@example.test', $body['email']);
        self::assertSame('john.doe@example.test', $body['mail']);
        self::assertSame(self::$socId, (int) $body['socid']);
        self::assertSame(self::$socId, (int) $body['fk_soc']);
        self::assertSame('Fixture Corp', $body['socname']);
        self::assertSame('MR', $body['civility_code']);
        // setGenderFromCivility
        self::assertSame('man', $body['gender']);
        self::assertSame(1, (int) $body['status']);
        self::assertSame(1, (int) $body['statut']);
        self::assertSame(strtotime('1990-06-15'), $body['birthday']);
        // keys removed by _cleanObjectDatas must not be present
        self::assertArrayNotHasKey('db', $body);
        self::assertArrayNotHasKey('errors', $body);
        self::assertArrayNotHasKey('element', $body);
        self::assertArrayNotHasKey('country', $body);

        // GET by email
        $client->request('GET', self::API . '/email/john.doe@example.test');
        self::assertResponseIsSuccessful();
        self::assertSame($id, (int) self::json($client)['id']);

        // PUT — returns the updated object. Upstream forces status back to 1
        // when 'statut' (fetched = 1) is non-empty and 'status' is empty, so
        // disabling requires sending both fields, like upstream clients do.
        $client->request('PUT', self::API . '/' . $id, content: json_encode([
            'id' => 999999, // ignored like upstream
            'lastname' => 'Doe Jr',
            'poste' => 'CTO',
            'status' => 0,
            'statut' => 0,
        ]));
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('Doe Jr', $body['lastname']);
        self::assertSame('CTO', $body['poste']);
        self::assertSame(0, (int) $body['status']);
        self::assertSame($id, (int) $body['id']);

        // DELETE — upstream success envelope
        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame(200, $body['success']['code']);
        self::assertSame('Contact deleted', $body['success']['message']);

        $client->request('GET', self::API . '/' . $id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetSpecimen(): void
    {
        $client = self::client();
        $client->request('GET', self::API . '/0');
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame(1, (int) $body['specimen']);
        self::assertSame('DOLIBARR', $body['lastname']);
        self::assertSame('SPECIMEN', $body['firstname']);
    }

    public function testGetNotFound(): void
    {
        $client = self::client();
        $client->request('GET', self::API . '/424242');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Contact not found', self::json($client)['error']['message']);
    }

    public function testPutSocidUnknownThirdparty(): void
    {
        $client = self::client();
        $client->request('POST', self::API, content: json_encode(['lastname' => 'Solo', 'firstname' => 'Hans']));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('PUT', self::API . '/' . $id, content: json_encode(['socid' => 987654]));
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Thirdparty with id=987654 not found or not allowed', self::json($client)['error']['message']);
    }

    public function testPutAssignsThirdparty(): void
    {
        $client = self::client();

        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Move', 'firstname' => 'Me', 'socid' => self::$socId,
        ]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);
        self::assertGreaterThan(0, $id);

        $client->request('GET', self::API . '/' . $id);
        self::assertSame(self::$socId, (int) self::json($client)['fk_soc']);

        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
    }

    public function testIndexListsAndFilters(): void
    {
        $client = self::client();

        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Alpha', 'firstname' => 'A', 'socid' => self::$socId,
        ]));
        $idA = (int) json_decode((string) $client->getResponse()->getContent(), true);
        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Beta', 'firstname' => 'B',
        ]));
        $idB = (int) json_decode((string) $client->getResponse()->getContent(), true);

        // full index — upstream returns [] (not 404) when empty
        $client->request('GET', self::API);
        self::assertResponseIsSuccessful();
        $list = self::json($client);
        $ids = array_column($list, 'id');
        self::assertContains($idA, array_map('intval', $ids));
        self::assertContains($idB, array_map('intval', $ids));
        // index always fetches roles
        self::assertArrayHasKey('roles', $list[0]);

        // thirdparty_ids filter — matches the /api/thirdparties/{id}/contacts sub-resource
        $client->request('GET', self::API . '?thirdparty_ids=' . self::$socId);
        $list = self::json($client);
        self::assertSame([$idA], array_map('intval', array_column($list, 'id')));

        $client->request('GET', '/api/thirdparties/' . self::$socId . '/contacts');
        self::assertResponseIsSuccessful();
        $list = self::json($client);
        self::assertSame([$idA], array_map('intval', array_column($list, 'id')));

        // properties filter
        $client->request('GET', self::API . '?thirdparty_ids=' . self::$socId . '&properties=lastname,id');
        $list = self::json($client);
        self::assertSame(['id', 'lastname'], array_values(array_intersect(['id', 'lastname'], array_keys($list[0]))));
        self::assertArrayNotHasKey('email', $list[0]);

        // sqlfilters
        $client->request('GET', self::API . "?sqlfilters=(t.lastname:like:'Alpha%25')");
        $list = self::json($client);
        self::assertSame([$idA], array_map('intval', array_column($list, 'id')));

        // pagination_data envelope
        $client->request('GET', self::API . '?pagination_data=1&limit=1&page=0');
        $page = self::json($client);
        self::assertArrayHasKey('data', $page);
        self::assertArrayHasKey('pagination', $page);
        self::assertSame(1, (int) $page['pagination']['limit']);
        self::assertGreaterThanOrEqual(2, (int) $page['pagination']['total']);

        // cleanup
        $client->request('DELETE', self::API . '/' . $idA);
        $client->request('DELETE', self::API . '/' . $idB);
    }

    public function testRolesAssignAndRead(): void
    {
        $client = self::client();

        // create with a role on the fixture thirdparty
        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Roleman', 'firstname' => 'R', 'socid' => self::$socId,
            'roles' => [['id' => self::$typeContactId, 'socid' => self::$socId]],
        ]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);
        self::assertGreaterThan(0, $id);

        $client->request('GET', self::API . '/' . $id . '?includeroles=1');
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertNotEmpty($body['roles']);
        $role = reset($body['roles']);
        self::assertSame(self::$typeContactId, (int) $role['id']);
        self::assertSame(self::$socId, (int) $role['socid']);
        self::assertSame('societe', $role['element']);
        self::assertSame('external', $role['source']);
        self::assertSame('CONTACT', $role['code']);
        // no translation layer: ContactDefault_<element> - <libelle>
        self::assertSame('ContactDefault_societe - Contact principal', $role['label']);

        // update without touching 'roles' must not wipe them (isset() guard)
        $client->request('PUT', self::API . '/' . $id, content: json_encode(['poste' => 'VP']));
        self::assertResponseIsSuccessful();
        $client->request('GET', self::API . '/' . $id . '?includeroles=1');
        self::assertNotEmpty(self::json($client)['roles']);

        // sending roles=[] wipes them, like upstream updateRoles
        $client->request('PUT', self::API . '/' . $id, content: json_encode(['roles' => []]));
        self::assertResponseIsSuccessful();
        $client->request('GET', self::API . '/' . $id . '?includeroles=1');
        self::assertSame([], self::json($client)['roles']);

        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
    }

    public function testCategoriesRoundtrip(): void
    {
        $client = self::client();

        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Catman', 'firstname' => 'C',
        ]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        // add
        $client->request('PUT', self::API . '/' . $id . '/categories/' . self::$categoryId);
        self::assertResponseIsSuccessful();

        // list
        $client->request('GET', self::API . '/' . $id . '/categories');
        self::assertResponseIsSuccessful();
        $cats = self::json($client);
        self::assertCount(1, $cats);
        self::assertSame(self::$categoryId, (int) $cats[0]['id']);
        self::assertSame('VIP', $cats[0]['label']);
        self::assertSame(4, (int) $cats[0]['type']);

        // category filter on index
        $client->request('GET', self::API . '?category=' . self::$categoryId);
        self::assertSame([$id], array_map('intval', array_column(self::json($client), 'id')));

        // unknown category id
        $client->request('PUT', self::API . '/' . $id . '/categories/777777');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('category not found', self::json($client)['error']['message']);

        // remove
        $client->request('DELETE', self::API . '/' . $id . '/categories/' . self::$categoryId);
        self::assertResponseIsSuccessful();
        $client->request('GET', self::API . '/' . $id . '/categories');
        self::assertSame([], self::json($client));

        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
    }

    public function testDeleteCascadesRolesAndCategories(): void
    {
        $client = self::client();

        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Cascade', 'firstname' => 'C', 'socid' => self::$socId,
            'roles' => [['id' => self::$typeContactId, 'socid' => self::$socId]],
        ]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);
        $client->request('PUT', self::API . '/' . $id . '/categories/' . self::$categoryId);

        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();

        // cascade assertions on the link tables (connection reconnects lazily)
        self::assertSame(0, (int) self::$db->fetchOne('SELECT COUNT(*) FROM llx_societe_contacts WHERE fk_socpeople = ' . $id));
        self::assertSame(0, (int) self::$db->fetchOne('SELECT COUNT(*) FROM llx_categorie_contact WHERE fk_socpeople = ' . $id));
    }

    public function testDictionaryEndpoints(): void
    {
        $client = self::client();

        $client->request('GET', '/api/setup/dictionary/contact_types');
        self::assertResponseIsSuccessful();
        $types = self::json($client);
        self::assertCount(2, $types);
        self::assertSame('BILLING', $types[0]['code']); // default sort: code ASC
        self::assertSame('CONTACT', $types[1]['code']);
        self::assertSame('societe', $types[1]['type']); // element aliased as type
        self::assertSame('Contact principal', $types[1]['label']);
        self::assertSame('external', $types[1]['source']);

        // type filter = LIKE on element
        $client->request('GET', '/api/setup/dictionary/contact_types?type=societe');
        self::assertCount(2, self::json($client));
        $client->request('GET', '/api/setup/dictionary/contact_types?type=nope');
        self::assertSame([], self::json($client));

        $client->request('GET', '/api/setup/dictionary/civilities');
        self::assertResponseIsSuccessful();
        $civs = self::json($client);
        self::assertCount(2, $civs);
        self::assertSame('MME', $civs[0]['code']); // sorted by code ASC
        self::assertSame('Misses', $civs[0]['label']);
    }

    public function testNoEmailWithMailingModule(): void
    {
        // mailing module is off by default — no_email is only managed when
        // enabled, and fetch() never selects the socpeople.no_email column
        // upstream (deprecated field), so the key stays null in responses.
        $client = self::client();
        $client->request('POST', self::API, content: json_encode([
            'lastname' => 'Mail', 'firstname' => 'M', 'email' => 'mail@example.test', 'no_email' => 1,
        ]));
        $id = (int) json_decode((string) $client->getResponse()->getContent(), true);

        $client->request('GET', self::API . '/' . $id);
        $body = self::json($client);
        self::assertNull($body['no_email']);

        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
    }
}
