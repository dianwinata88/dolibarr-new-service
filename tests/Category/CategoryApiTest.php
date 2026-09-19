<?php

declare(strict_types=1);

namespace App\Tests\Category;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contract tests for the categories API slice (port of
 * htdocs/categories/class/api_categories.class.php endpoints).
 * They exercise the HTTP surface end-to-end against the llx_* tables.
 */
final class CategoryApiTest extends WebTestCase
{
    private const API = '/api/categories';

    private static Connection $db;
    private static int $socId;
    private static int $contactId;

    public static function setUpBeforeClass(): void
    {
        self::createClient(); // boots the kernel
        self::$db = self::getContainer()->get('doctrine.dbal.default_connection');
        self::ensureKernelShutdown();

        // clean slate for the fixtures this class owns
        foreach (
            ['llx_categorie_contact', 'llx_categorie_fournisseur', 'llx_categorie_societe',
                     'llx_categories_extrafields', 'llx_categorie'] as $table
        ) {
            try {
                self::$db->executeStatement("DELETE FROM $table");
            } catch (\Throwable) {
            }
        }

        self::$db->executeStatement(
            "INSERT INTO llx_societe (nom, entity, client, fournisseur, status, datec)"
            . " VALUES ('CatTest Thirdparty', 1, 1, 1, 1, NOW())",
        );
        self::$socId = (int) self::$db->lastInsertId();

        self::$db->executeStatement(
            "INSERT INTO llx_socpeople (entity, lastname, firstname, fk_soc, statut)"
            . " VALUES (1, 'CatContact', 'John', ?, 1)",
            [self::$socId],
        );
        self::$contactId = (int) self::$db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            foreach (
                ['llx_categorie_contact', 'llx_categorie_fournisseur', 'llx_categorie_societe',
                         'llx_categories_extrafields', 'llx_categorie'] as $table
            ) {
                self::$db->executeStatement("DELETE FROM $table");
            }
            self::$db->executeStatement('DELETE FROM llx_socpeople WHERE rowid = ?', [self::$contactId]);
            self::$db->executeStatement('DELETE FROM llx_societe WHERE rowid = ?', [self::$socId]);
        } catch (\Throwable) {
        }
    }

    private static function client(): KernelBrowser
    {
        $client = self::createClient();
        $keys = explode(',', (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? 'dolibarr-dev-key'));
        $client->setServerParameter('HTTP_DOLAPIKEY', trim($keys[0]));

        return $client;
    }

    private static function json(KernelBrowser $client): mixed
    {
        return json_decode((string) $client->getResponse()->getContent(), true);
    }

    private static function createCategory(KernelBrowser $client, array $payload): int
    {
        $client->request('POST', self::API, content: json_encode($payload));
        self::assertResponseIsSuccessful();

        return (int) self::json($client);
    }

    // ------------------------------------------------------------------

    public function testRequiresApiKey(): void
    {
        $client = self::createClient();
        $client->request('GET', self::API);

        self::assertResponseStatusCodeSame(401);
    }

    public function testPostValidatesMandatoryFields(): void
    {
        $client = self::client();

        $client->request('POST', self::API, content: json_encode([]));
        self::assertResponseStatusCodeSame(400);
        self::assertSame('label field missing', self::json($client)['error']['message']);

        $client->request('POST', self::API, content: json_encode(['label' => 'X']));
        self::assertResponseStatusCodeSame(400);
        self::assertSame('type field missing', self::json($client)['error']['message']);
    }

    public function testCreateFetchUpdateDeleteRoundtrip(): void
    {
        $client = self::client();

        // POST — returns the new id (int), like upstream
        $id = self::createCategory($client, [
            'label' => 'VIP Customers',
            'type' => 2,
            'description' => 'Roundtrip category',
            'color' => 'ff8800',
            'position' => 7,
            'visible' => 1,
            'ref_ext' => 'EXT-CAT-1',
            'import_key' => 'imp-001',
        ]);
        self::assertGreaterThan(0, $id);

        // GET — upstream-shaped object
        $client->request('GET', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('VIP Customers', $body['label']);
        self::assertSame('Roundtrip category', $body['description']);
        self::assertSame('ff8800', $body['color']);
        self::assertSame(7, (int) $body['position']);
        self::assertSame(1, (int) $body['visible']);
        self::assertSame(2, (int) $body['type']);
        self::assertSame('EXT-CAT-1', $body['ref_ext']);
        self::assertSame('imp-001', $body['import_key']);
        self::assertSame(0, (int) $body['fk_parent']);
        self::assertSame(0, (int) $body['socid']);
        self::assertArrayHasKey('array_options', $body);
        self::assertArrayHasKey('childs', $body);
        self::assertSame([], $body['childs']);

        // PUT — returns the updated object
        $client->request('PUT', self::API . '/' . $id, content: json_encode([
            'description' => 'Updated description',
            'color' => '00ff00',
            'visible' => 0,
        ]));
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('Updated description', $body['description']);
        self::assertSame('00ff00', $body['color']);
        self::assertSame(0, (int) $body['visible']);

        // DELETE — upstream success envelope
        $client->request('DELETE', self::API . '/' . $id);
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame(200, $body['success']['code']);
        self::assertSame('Category deleted', $body['success']['message']);

        $client->request('GET', self::API . '/' . $id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetMissingReturns404(): void
    {
        $client = self::client();
        $client->request('GET', self::API . '/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('category not found', self::json($client)['error']['message']);
    }

    public function testDuplicateLabelSameLevelRejected(): void
    {
        $client = self::client();
        $id = self::createCategory($client, ['label' => 'DupLabel', 'type' => 2]);

        // same label + parent + type -> already_exists() -> 500 like upstream
        $client->request('POST', self::API, content: json_encode(['label' => 'DupLabel', 'type' => 2]));
        self::assertResponseStatusCodeSame(500);
        $body = self::json($client);
        self::assertSame('Error when creating category', $body['error']['message']);
        self::assertStringContainsString('This category already exists with this ref', (string) $body['error']['0']);

        // same label under a different type is allowed
        $other = self::createCategory($client, ['label' => 'DupLabel', 'type' => 4]);

        // PUT the second one to collide with type 2 via its own update path
        $client->request('PUT', self::API . '/' . $other, content: json_encode(['label' => 'OtherLabel', 'type' => 4]));
        self::assertResponseIsSuccessful();
    }

    public function testPutSelfParentRejected(): void
    {
        $client = self::client();
        $id = self::createCategory($client, ['label' => 'SelfParent', 'type' => 2]);

        $client->request('PUT', self::API . '/' . $id, content: json_encode(['fk_parent' => $id]));
        self::assertResponseStatusCodeSame(500);
        self::assertSame('A tag/category cannot be its own parent.', self::json($client)['error']['message']);
    }

    public function testGetTypes(): void
    {
        $client = self::client();
        $client->request('GET', self::API . '/types');

        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertSame('ProspectsOrCustomers', $body['customer']);
        self::assertSame('Suppliers', $body['supplier']);
        self::assertSame('Contacts', $body['contact']);
    }

    public function testIndexAndFilters(): void
    {
        $client = self::client();
        $a = self::createCategory($client, ['label' => 'IdxAlpha', 'type' => 2]);
        self::createCategory($client, ['label' => 'IdxBeta', 'type' => 2]);
        self::createCategory($client, ['label' => 'IdxContact', 'type' => 4]);

        // type filter accepts upstream string codes
        $client->request('GET', self::API . '?type=customer');
        self::assertResponseIsSuccessful();
        $labels = array_column(self::json($client), 'label');
        self::assertContains('IdxAlpha', $labels);
        self::assertNotContains('IdxContact', $labels);

        // unknown type maps to -1 -> empty list
        $client->request('GET', self::API . '?type=notacode');
        self::assertResponseIsSuccessful();
        self::assertSame([], self::json($client));

        // sqlfilters in the universal search syntax
        $client->request('GET', self::API . '?sqlfilters=' . urlencode("(t.label:like:'Idx%')"));
        self::assertResponseIsSuccessful();
        $labels = array_column(self::json($client), 'label');
        self::assertContains('IdxAlpha', $labels);
        self::assertContains('IdxBeta', $labels);
        self::assertContains('IdxContact', $labels);

        // properties restriction
        $client->request('GET', self::API . '?type=2&properties=id,label');
        self::assertResponseIsSuccessful();
        $first = self::json($client)[0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('label', $first);
        self::assertArrayNotHasKey('description', $first);
    }

    public function testIndexRejectsBadSqlfilters(): void
    {
        $client = self::client();
        // unbalanced parenthesis -> forgeSQLFromUniversalSearchCriteria errors
        $client->request('GET', self::API . '?sqlfilters=' . urlencode("((t.label:like:'x')"));

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString(
            'Error when validating parameter sqlfilters',
            (string) self::json($client)['error']['message']
        );

        // syntactically valid filter on an unknown column -> SQL error -> 503 like upstream
        $client->request('GET', self::API . '?sqlfilters=' . urlencode("(t.nosuchcol:=:1)"));
        self::assertResponseStatusCodeSame(503);
        self::assertStringContainsString(
            'Error when retrieve category list',
            (string) self::json($client)['error']['message']
        );
    }

    public function testHierarchyChildsAndReparentOnDelete(): void
    {
        $client = self::client();
        $parent = self::createCategory($client, ['label' => 'ParentCat', 'type' => 2]);
        $child = self::createCategory($client, ['label' => 'ChildCat', 'type' => 2, 'fk_parent' => $parent]);

        $client->request('GET', self::API . '/' . $parent . '?include_childs=1');
        self::assertResponseIsSuccessful();
        $body = self::json($client);
        self::assertCount(1, $body['childs']);
        self::assertSame($child, (int) $body['childs'][0]['id']);

        // deleting the parent moves the child up one level (upstream FIX #1317)
        $client->request('DELETE', self::API . '/' . $parent);
        self::assertResponseIsSuccessful();

        $client->request('GET', self::API . '/' . $child);
        self::assertResponseIsSuccessful();
        self::assertSame(0, (int) self::json($client)['fk_parent']);
    }

    public function testLinkUnlinkCustomerById(): void
    {
        $client = self::client();
        $catId = self::createCategory($client, ['label' => 'CustLink', 'type' => 2]);

        $client->request('POST', self::API . '/' . $catId . '/objects/customer/' . self::$socId);
        self::assertResponseIsSuccessful();
        self::assertSame('Objects successfully linked to the category', self::json($client)['success']['message']);

        $junction = self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_categorie_societe WHERE fk_categorie = ? AND fk_soc = ?',
            [$catId, self::$socId],
        );
        self::assertSame(1, (int) $junction);

        // relinking the same object is a no-op upstream (ALREADY_EXISTS swallowed)
        $client->request('POST', self::API . '/' . $catId . '/objects/customer/' . self::$socId);
        self::assertResponseIsSuccessful();

        // categories of the object
        $client->request('GET', self::API . '/object/customer/' . self::$socId);
        self::assertResponseIsSuccessful();
        $ids = array_column(self::json($client), 'id');
        self::assertContains($catId, array_map('intval', $ids));

        // objects of the category
        $client->request('GET', self::API . '/' . $catId . '/objects?type=customer&onlyids=1');
        self::assertResponseIsSuccessful();
        self::assertContains(self::$socId, array_map('intval', (array) self::json($client)));

        // full objects list
        $client->request('GET', self::API . '/' . $catId . '/objects?type=customer');
        self::assertResponseIsSuccessful();
        $objects = self::json($client);
        self::assertSame(self::$socId, (int) $objects[0]['id']);

        // unlink
        $client->request('DELETE', self::API . '/' . $catId . '/objects/customer/' . self::$socId);
        self::assertResponseIsSuccessful();
        self::assertSame('Objects successfully unlinked from the category', self::json($client)['success']['message']);
        $junction = self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_categorie_societe WHERE fk_categorie = ? AND fk_soc = ?',
            [$catId, self::$socId],
        );
        self::assertSame(0, (int) $junction);
    }

    public function testLinkCustomerByRef(): void
    {
        $client = self::client();
        $catId = self::createCategory($client, ['label' => 'CustRefLink', 'type' => 2]);

        // upstream matches Societe::fetch(0, $ref) -> nom exact match
        $client->request('POST', self::API . '/' . $catId . '/objects/customer/ref/CatTest Thirdparty');
        self::assertResponseIsSuccessful();

        $junction = self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_categorie_societe WHERE fk_categorie = ? AND fk_soc = ?',
            [$catId, self::$socId],
        );
        self::assertSame(1, (int) $junction);

        $client->request('DELETE', self::API . '/' . $catId . '/objects/customer/ref/CatTest Thirdparty');
        self::assertResponseIsSuccessful();
    }

    public function testLinkUnlinkContactAndSupplier(): void
    {
        $client = self::client();
        $contactCat = self::createCategory($client, ['label' => 'ContactLink', 'type' => 4]);
        $supplierCat = self::createCategory($client, ['label' => 'SupplierLink', 'type' => 1]);

        $client->request('POST', self::API . '/' . $contactCat . '/objects/contact/' . self::$contactId);
        self::assertResponseIsSuccessful();
        $junction = self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_categorie_contact WHERE fk_categorie = ? AND fk_socpeople = ?',
            [$contactCat, self::$contactId],
        );
        self::assertSame(1, (int) $junction);

        $client->request('GET', self::API . '/object/contact/' . self::$contactId);
        self::assertResponseIsSuccessful();
        self::assertContains($contactCat, array_map('intval', array_column(self::json($client), 'id')));

        $client->request('DELETE', self::API . '/' . $contactCat . '/objects/contact/' . self::$contactId);
        self::assertResponseIsSuccessful();

        $client->request('POST', self::API . '/' . $supplierCat . '/objects/supplier/' . self::$socId);
        self::assertResponseIsSuccessful();
        $junction = self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_categorie_fournisseur WHERE fk_categorie = ? AND fk_soc = ?',
            [$supplierCat, self::$socId],
        );
        self::assertSame(1, (int) $junction);
    }

    public function testLinkUnknownTypeReturns400(): void
    {
        $client = self::client();
        $catId = self::createCategory($client, ['label' => 'BadTypeLink', 'type' => 2]);

        $client->request('POST', self::API . '/' . $catId . '/objects/notatype/' . self::$socId);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('this type is not recognized yet.', self::json($client)['error']['message']);
    }

    public function testLinkMissingObjectReturns500(): void
    {
        $client = self::client();
        $catId = self::createCategory($client, ['label' => 'MissingObjLink', 'type' => 2]);

        // upstream: $object->fetch() returning <= 0 -> 500 'Error when fetching object'
        $client->request('POST', self::API . '/' . $catId . '/objects/customer/999999');
        self::assertResponseStatusCodeSame(500);
        self::assertSame('Error when fetching object', self::json($client)['error']['message']);
    }

    public function testDeleteRemovesJunctions(): void
    {
        $client = self::client();
        $catId = self::createCategory($client, ['label' => 'DelWithLinks', 'type' => 2]);

        $client->request('POST', self::API . '/' . $catId . '/objects/customer/' . self::$socId);
        self::assertResponseIsSuccessful();

        $client->request('DELETE', self::API . '/' . $catId);
        self::assertResponseIsSuccessful();

        $junction = self::$db->fetchOne(
            'SELECT COUNT(*) FROM llx_categorie_societe WHERE fk_categorie = ?',
            [$catId],
        );
        self::assertSame(0, (int) $junction);
    }
}
