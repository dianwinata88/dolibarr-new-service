<?php

declare(strict_types=1);

namespace App\Category;

use Doctrine\DBAL\Connection;

/**
 * Port of Categorie (htdocs/categories/class/categorie.class.php) restricted to
 * the parts used by api_categories.class.php for the CRM slice: fetch, create,
 * update, delete, add_type/del_type, get_filles, getListForItem,
 * getObjectsInCateg, containsObject, already_exists.
 *
 * SQL is kept as close to upstream as possible (same WHERE/entity semantics);
 * objects are plain associative rows rather than Categorie instances. Error
 * strings replicate the upstream $this->error/$this->errors surface through
 * getLastError()/getErrors().
 */
final class CategoryService
{
    /** Upstream Categorie::MAP_ID (type string => id used in llx_categorie.type). */
    public const MAP_ID = [
        'product' => 0,
        'service' => 0,
        'supplier' => 1,
        'customer' => 2,
        'member' => 3,
        'contact' => 4,
        'bank_account' => 5,
        'project' => 6,
        'user' => 7,
        'bank_line' => 8,
        'warehouse' => 9,
        'actioncomm' => 10,
        'website_page' => 11,
        'ticket' => 12,
        'knowledgemanagement' => 13,
        'fichinter' => 14,
        'order' => 16,
        'invoice' => 17,
        'supplier_order' => 20,
        'supplier_invoice' => 21,
        'supplier_proposal' => 22,
        'propal' => 23,
        'project_task' => 24,
        'mo' => 25,
    ];

    /** Upstream MAP_CAT_FK: fk column in the junction table when != type. */
    public const MAP_CAT_FK = [
        'customer' => 'soc',
        'supplier' => 'soc',
        'contact' => 'socpeople',
        'bank_account' => 'account',
    ];

    /** Upstream MAP_CAT_TABLE: llx_categorie_<x> table suffix when != type. */
    public const MAP_CAT_TABLE = [
        'customer' => 'societe',
        'supplier' => 'fournisseur',
        'bank_account' => 'account',
    ];

    /** Upstream MAP_OBJ_TABLE: llx_<x> object table when != type. */
    public const MAP_OBJ_TABLE = [
        'customer' => 'societe',
        'supplier' => 'societe',
        'member' => 'adherent',
        'contact' => 'socpeople',
        'account' => 'bank_account',
        'project' => 'projet',
        'warehouse' => 'entrepot',
        'knowledgemanagement' => 'knowledgemanagement_knowledgerecord',
        'fichinter' => 'fichinter',
        'order' => 'commande',
        'invoice' => 'facture',
        'supplier_order' => 'commande_fournisseur',
        'supplier_invoice' => 'facture_fourn',
        'project_task' => 'projet_task',
        'mo' => 'mrp_mo',
    ];

    /** Upstream Categorie::$MAP_TYPE_TITLE_AREA. */
    public const MAP_TYPE_TITLE_AREA = [
        'product' => 'Products',
        'service' => 'Services',
        'customer' => 'ProspectsOrCustomers',
        'supplier' => 'Suppliers',
        'member' => 'Members',
        'contact' => 'Contacts',
        'user' => 'Users',
        'account' => 'Accounts',
        'bank_account' => 'BankAccounts',
        'bank_line' => 'BankTransactions',
        'project' => 'Projects',
        'warehouse' => 'Warehouse',
        'actioncomm' => 'AgendaEvents',
        'website_page' => 'WebsitePages',
        'ticket' => 'Tickets',
        'knowledgemanagement' => 'KnowledgeRecords',
        'fichinter' => 'Interventions',
        'order' => 'Orders',
        'invoice' => 'Invoices',
        'supplier_order' => 'SuppliersOrders',
        'supplier_invoice' => 'SuppliersInvoices',
        'propal' => 'Proposals',
        'supplier_proposal' => 'SupplierProposals',
        'project_task' => 'Tasks',
        'mo' => 'MOs',
    ];

    /** Element name used by getEntity() for the linked object's table. */
    private const MAP_OBJ_ELEMENT = [
        'customer' => 'societe',
        'supplier' => 'societe',
        'contact' => 'contact',
        'product' => 'product',
        'member' => 'adherent',
        'actioncomm' => 'actioncomm',
        'project' => 'projet',
    ];

    /** Columns that are never extra field values in an extrafields table. */
    private const EXTRAFIELDS_INTERNAL_COLUMNS = ['rowid', 'tms', 'fk_object', 'import_key'];

    /** Full column list of the upstream Categorie::fetch() SELECT. */
    private const CATEGORIE_SELECT_COLS = 'rowid, fk_parent, entity, label, description, color, position, fk_soc,'
        . ' visible, type, ref_ext, date_creation, tms, fk_user_creat, fk_user_modif, import_key';

    private ?string $lastError = null;

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function resetErrors(): void
    {
        $this->lastError = null;
        $this->errors = [];
    }

    private function fail(string $error): void
    {
        $this->lastError = $error;
        $this->errors[] = $error;
    }

    /** MAP_OBJ_TABLE lookup returning null when the type has no mapping. */
    private function mappedObjTable(string $type): ?string
    {
        return self::MAP_OBJ_TABLE[$type] ?? null;
    }

    /**
     * Port of Categorie::fetch($id, $label, $type, $ref_ext).
     *
     * @return array<string, mixed>|null raw llx_categorie row, null when not found/error
     */
    public function fetch(int $id = 0, string $label = '', int|string|null $type = null, string $refExt = ''): ?array
    {
        $this->resetErrors();

        if ($id === 0 && $label === '' && $refExt === '') {
            $this->fail('No category to search for');

            return null;
        }
        if ($type !== null && !is_numeric($type)) {
            // Upstream: $this->MAP_ID[$type] (null when unknown -> no type filter)
            $type = self::MAP_ID[$type] ?? null;
        }

        try {
            if ($id !== 0) {
                $row = $this->db->fetchAssociative(
                    'SELECT ' . self::CATEGORIE_SELECT_COLS . ' FROM llx_categorie WHERE rowid = ?',
                    [$id],
                );
            } elseif ($refExt !== '') {
                $row = $this->db->fetchAssociative(
                    'SELECT ' . self::CATEGORIE_SELECT_COLS . ' FROM llx_categorie WHERE ref_ext LIKE ?',
                    [$refExt],
                );
            } else {
                $sql = 'SELECT ' . self::CATEGORIE_SELECT_COLS . ' FROM llx_categorie'
                    . ' WHERE label = ' . $this->db->quote($label)
                    . ' AND entity IN (' . $this->config->getEntity('category') . ')';
                if ($type !== null) {
                    $sql .= ' AND type = ' . ((int) $type);
                }
                $row = $this->db->fetchAssociative($sql);
            }
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return null;
        }

        if ($row === false) {
            $this->fail('No category found');

            return null;
        }

        return $row;
    }

    /**
     * Port of Categorie::create(). Stores via raw SQL like upstream.
     *
     * @param array<string, mixed> $props sanitized object properties (label,
     *        type, fk_parent, description, color, position, socid, visible,
     *        ref_ext, import_key, array_options, ...)
     * @return int new rowid (>0), or <0: -1 SQL error, -2 new id unknown,
     *         -3 invalid, -4 category already exists
     */
    public function create(array $props, int $userId): int
    {
        $this->resetErrors();

        $type = $props['type'] ?? null;
        if (!is_numeric($type)) {
            // Upstream casts a missing MAP_ID key to null -> (int) 0
            $type = self::MAP_ID[(string) $type] ?? null;
        }
        $type = (int) $type;

        // Clean parameters (verbatim from upstream)
        $label = trim((string) ($props['label'] ?? ''));
        $description = trim((string) ($props['description'] ?? ''));
        $color = trim((string) ($props['color'] ?? ''));
        $position = (int) ($props['position'] ?? 0);
        $importKey = isset($props['import_key']) ? trim((string) $props['import_key']) : null;
        $refExt = trim((string) ($props['ref_ext'] ?? ''));
        $visible = empty($props['visible']) ? 0 : (int) $props['visible'];
        $fkParent = ($props['fk_parent'] ?? '') !== '' ? (int) $props['fk_parent'] : 0;
        $socid = (int) ($props['socid'] ?? 0);

        if ($this->alreadyExists(['id' => 0, 'type' => $type, 'fk_parent' => $fkParent, 'label' => $label]) === 1) {
            // $langs->trans('ImpossibleAddCat', $label).' : '.$langs->trans('CategoryExistsAtSameLevel')
            $this->fail(
                'Impossible to add the tag/category ' . $label . ' : This category already exists with this ref'
            );

            return -4;
        }

        $data = [
            'fk_parent' => $fkParent,
            'label' => $label,
            'description' => $description,
            'color' => $color,
            'position' => $position,
            'visible' => $visible,
            'type' => $type,
            'import_key' => $importKey !== '' ? $importKey : null,
            'ref_ext' => $refExt !== '' ? $refExt : null,
            'entity' => $this->config->entity(),
            'date_creation' => date('Y-m-d H:i:s'),
            'fk_user_creat' => $userId,
        ];
        if ($this->config->getString('CATEGORY_ASSIGNED_TO_A_CUSTOMER') !== '') {
            $data['fk_soc'] = $socid > 0 ? $socid : null;
        }

        try {
            // Upstream wraps the insert + insertExtraFields() in one transaction
            $id = $this->db->transactional(function (Connection $db) use ($data, $props): int {
                $db->insert('llx_categorie', $data);
                $newId = (int) $db->lastInsertId();
                if ($newId <= 0) {
                    return -2;
                }
                $arrayOptions = is_array($props['array_options'] ?? null) ? $props['array_options'] : [];
                $this->saveExtrafields($newId, $arrayOptions);

                return $newId;
            });
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        if ($id <= 0) {
            return -2;
        }

        return $id;
    }

    /**
     * Port of Categorie::update(). Only the fields upstream writes are
     * persisted (label, description, ref_ext, color, position, fk_soc when
     * CATEGORY_ASSIGNED_TO_A_CUSTOMER, visible, fk_parent, fk_user_modif).
     *
     * @param array<string, mixed> $cat   current category row (fetch())
     * @param array<string, mixed> $props sanitized request fields merged over
     * @return int 1 OK, <0 KO
     */
    public function update(array $cat, array $props, int $userId): int
    {
        $this->resetErrors();

        $merged = array_merge($cat, $props);
        $id = (int) $cat['rowid'];

        $label = trim((string) ($merged['label'] ?? ''));
        $description = trim((string) ($merged['description'] ?? ''));
        $refExt = trim((string) ($merged['ref_ext'] ?? ''));
        $fkParent = ($merged['fk_parent'] ?? '') !== '' ? (int) $merged['fk_parent'] : 0;
        $visible = ($merged['visible'] ?? '') !== '' ? (int) $merged['visible'] : 0;
        $color = trim((string) ($merged['color'] ?? ''));
        $position = (int) ($merged['position'] ?? 0);
        $socid = (int) ($merged['socid'] ?? $merged['fk_soc'] ?? 0);

        $type = $merged['type'] ?? null;
        if (!is_numeric($type)) {
            $type = self::MAP_ID[(string) $type] ?? null;
        }
        $type = (int) $type;

        if ($fkParent > 0 && $fkParent === $id) {
            $this->fail('A tag/category cannot be its own parent.');

            return -1;
        }

        if ($this->alreadyExists(['id' => $id, 'type' => $type, 'fk_parent' => $fkParent, 'label' => $label]) === 1) {
            // 'ImpossibleUpdateCat' is an untranslated key upstream -> the key itself is returned.
            $this->fail('ImpossibleUpdateCat : This category already exists with this ref');

            return -1;
        }

        $data = [
            'label' => $label,
            'description' => $description,
            'ref_ext' => $refExt,
            'color' => $color,
            'position' => $position,
            'visible' => $visible,
            'fk_parent' => $fkParent,
            'fk_user_modif' => $userId,
        ];
        if ($this->config->getString('CATEGORY_ASSIGNED_TO_A_CUSTOMER') !== '') {
            $data['fk_soc'] = $socid > 0 ? $socid : null;
        }

        try {
            $this->db->transactional(function (Connection $db) use ($data, $id, $props): void {
                $db->update('llx_categorie', $data, ['rowid' => $id]);
                $this->saveExtrafields($id, is_array($props['array_options'] ?? null) ? $props['array_options'] : []);
            });
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        return 1;
    }

    /**
     * Port of Categorie::delete(): children are moved up one level, all
     * llx_categorie_* junction rows and extrafields are removed, then the row.
     *
     * @param array<string, mixed> $cat category row (fetch())
     */
    public function delete(array $cat): int
    {
        $this->resetErrors();

        $id = (int) $cat['rowid'];
        $fkParent = ($cat['fk_parent'] ?? '') !== '' ? (int) $cat['fk_parent'] : 0;

        try {
            $this->db->transactional(function (Connection $db) use ($id, $fkParent): void {
                // FIX #1317 upstream: move child categories up one level
                $db->executeStatement('UPDATE llx_categorie SET fk_parent = ? WHERE fk_parent = ?', [$fkParent, $id]);

                // Junction tables of upstream's $arraydelete that exist in this schema.
                $junctionDeletes = [
                    'llx_categorie_account' => 'fk_categorie',
                    'llx_categorie_actioncomm' => 'fk_categorie',
                    'llx_categorie_contact' => 'fk_categorie',
                    'llx_categorie_fournisseur' => 'fk_categorie',
                    'llx_categorie_member' => 'fk_categorie',
                    'llx_categorie_user' => 'fk_categorie',
                    'llx_categorie_product' => 'fk_categorie',
                    'llx_categorie_project' => 'fk_categorie',
                    'llx_categorie_societe' => 'fk_categorie',
                    'llx_category_bankline' => 'fk_categ',
                    'llx_categorie_lang' => 'fk_category',
                ];
                foreach ($junctionDeletes as $table => $field) {
                    if ($db->createSchemaManager()->tablesExist([$table])) {
                        $db->executeStatement(
                            'DELETE FROM ' . $this->utils->sanitizeIdentifier($table)
                            . ' WHERE ' . $this->utils->sanitizeIdentifier($field) . ' = ' . (int) $id,
                        );
                    }
                }

                // deleteExtraFields()
                if ($db->createSchemaManager()->tablesExist(['llx_categories_extrafields'])) {
                    $db->executeStatement('DELETE FROM llx_categories_extrafields WHERE fk_object = ?', [$id]);
                }

                $db->executeStatement('DELETE FROM llx_categorie WHERE rowid = ?', [$id]);
            });
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        return 1;
    }

    /**
     * Port of Categorie::add_type().
     *
     * @return int 1 OK, -1 SQL error, -3 already linked (DB_ERROR_RECORD_ALREADY_EXISTS)
     */
    public function addType(int $catId, int $objId, string $type): int
    {
        $this->resetErrors();

        if ($catId === -1) {
            return -2;
        }

        $table = 'llx_categorie_' . (self::MAP_CAT_TABLE[$type] ?? $type);
        $fk = self::MAP_CAT_FK[$type] ?? $type;

        try {
            $this->db->insert($this->utils->sanitizeIdentifier($table), [
                'fk_categorie' => $catId,
                'fk_' . $this->utils->sanitizeIdentifier($fk) => $objId,
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '1062')) {
                $this->fail('DB_ERROR_RECORD_ALREADY_EXISTS');

                return -3;
            }
            $this->fail($e->getMessage());

            return -1;
        }

        // CATEGORIE_RECURSIV_ADD: also link to parent categories recursively
        if ($this->config->getString('CATEGORIE_RECURSIV_ADD') !== '') {
            try {
                $parentId = $this->db->fetchOne('SELECT fk_parent FROM llx_categorie WHERE rowid = ?', [$catId]);
            } catch (\Throwable $e) {
                $this->fail($e->getMessage());

                return -1;
            }
            $parentId = (int) ($parentId ?: 0);
            if ($parentId > 0 && $this->containsObject($parentId, $type, $objId) === 0) {
                $result = $this->addType($parentId, $objId, $type);
                if ($result < 0) {
                    return -1;
                }
            }
        }

        return 1;
    }

    /**
     * Port of Categorie::del_type().
     */
    public function delType(int $catId, int $objId, string $type): int
    {
        $this->resetErrors();

        // Upstream backward-compatibility aliases
        if ($type === 'societe') {
            $type = 'customer';
        } elseif ($type === 'fournisseur') {
            $type = 'supplier';
        }

        $table = 'llx_categorie_' . (self::MAP_CAT_TABLE[$type] ?? $type);
        $fk = self::MAP_CAT_FK[$type] ?? $type;

        try {
            $this->db->executeStatement(
                'DELETE FROM ' . $this->utils->sanitizeIdentifier($table)
                . ' WHERE fk_categorie = ' . (int) $catId
                . ' AND fk_' . $this->utils->sanitizeIdentifier($fk) . ' = ' . (int) $objId,
            );
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        return 1;
    }

    /**
     * Port of Categorie::containsObject().
     *
     * @return int number of occurrences, -1 on error
     */
    public function containsObject(int $catId, string $type, int $objId): int
    {
        $table = 'llx_categorie_' . (self::MAP_CAT_TABLE[$type] ?? $type);
        $fk = self::MAP_CAT_FK[$type] ?? $type;

        try {
            $nb = $this->db->fetchOne(
                'SELECT COUNT(*) as nb FROM ' . $this->utils->sanitizeIdentifier($table)
                . ' WHERE fk_categorie = ' . (int) $catId
                . ' AND fk_' . $this->utils->sanitizeIdentifier($fk) . ' = ' . (int) $objId,
            );
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        return (int) $nb;
    }

    /**
     * Port of Categories::index() SQL: rowid list of categories for the list
     * endpoint (entity scope, optional type filter, universal sqlfilters
     * fragment, orderBy/plimit).
     *
     * @return array<int, array<string, mixed>>|null rows (['rowid'=>..]), null on error
     */
    public function listIds(
        string $type,
        string $sqlfiltersFragment,
        string $sortfield,
        string $sortorder,
        int $limit,
        int $page
    ): ?array {
        $sql = 'SELECT t.rowid';
        $sql .= ' FROM llx_categorie AS t LEFT JOIN llx_categories_extrafields AS ef ON (ef.fk_object = t.rowid)';
        $sql .= ' WHERE t.entity IN (' . $this->config->getEntity('category') . ')';
        if ($type !== '') {
            if (is_numeric($type)) {
                $sql .= ' AND t.type = ' . ((int) $type);
            } else {
                $sql .= ' AND t.type = ' . ((int) (array_key_exists($type, self::MAP_ID) ? self::MAP_ID[$type] : -1));
            }
        }

        $sql .= $sqlfiltersFragment;

        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;
            $sql .= ' LIMIT ' . ($limit + 1) . ' OFFSET ' . $offset;
        }

        try {
            return $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return null;
        }
    }

    /**
     * Port of Categorie::get_filles(): direct children of a category.
     *
     * @return array<int, array<string, mixed>>|null rows, null on error
     */
    public function getFilles(int $catId): ?array
    {
        try {
            $ids = $this->db->fetchFirstColumn(
                'SELECT rowid FROM llx_categorie WHERE fk_parent = ' . (int) $catId
                . ' AND entity IN (' . $this->config->getEntity('category') . ')',
            );
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return null;
        }

        $cats = [];
        foreach ($ids as $id) {
            $cat = $this->fetch((int) $id);
            if ($cat !== null) {
                $cats[] = $cat;
            }
        }

        return $cats;
    }

    /**
     * Port of Categorie::getListForItem(): categories linked to an object.
     *
     * @return array<int, array<string, mixed>>|int rows (upstream-shaped item
     *         arrays), -1 on error
     */
    public function getListForItem(
        int $id,
        string $type = 'customer',
        string $sortfield = 's.rowid',
        string $sortorder = 'ASC',
        int $limit = 0,
        int $page = 0
    ): array|int {
        $type = (string) $this->utils->sanitizeVal($type, 'aZ09');

        $subType = $type;
        $subcolName = 'fk_' . $type;
        if ($type === 'customer') {
            $subType = 'societe';
            $subcolName = 'fk_soc';
        }
        if ($type === 'supplier') {
            $subType = 'fournisseur';
            $subcolName = 'fk_soc';
        }
        if ($type === 'contact') {
            $subcolName = 'fk_socpeople';
        }

        $idoftype = self::MAP_ID[$type] ?? -1;

        $sql = 'SELECT s.rowid';
        $sqlfields = $sql;
        $sql .= ' FROM llx_categorie as s, llx_categorie_' . $this->utils->sanitizeIdentifier($subType) . ' as sub';
        $sql .= ' WHERE s.entity IN (' . $this->config->getEntity('category') . ')';
        $sql .= ' AND s.type = ' . ((int) $idoftype);
        $sql .= ' AND s.rowid = sub.fk_categorie';
        $sql .= ' AND sub.' . $this->utils->sanitizeIdentifier($subcolName) . ' = ' . (int) $id;

        $offset = 0;
        if (!$this->config->getBool('MAIN_DISABLE_FULL_SCANLIST')) {
            $sqlforcount = (string) preg_replace(
                '/^' . preg_quote($sqlfields, '/') . '/',
                'SELECT COUNT(*) as nbtotalofrecords',
                $sql
            );
            $sqlforcount = (string) preg_replace('/GROUP BY .*$/', '', $sqlforcount);

            try {
                $nbtotalofrecords = (int) $this->db->fetchOne($sqlforcount);
            } catch (\Throwable $e) {
                $this->fail($e->getMessage());

                return -1;
            }

            if ($limit >= $nbtotalofrecords && $page > 0) {
                return [];
            }

            if (($page * $limit) >= $nbtotalofrecords) {
                $page = 0;
            }
        }

        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql .= ' LIMIT ' . ($limit + 1) . ' OFFSET ' . $offset;
        }

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        $categories = [];
        $num = count($rows);
        $min = min($num, ($limit <= 0 ? $num : $limit));
        for ($i = 0; $i < $min; $i++) {
            $cat = $this->fetch((int) $rows[$i]['rowid']);
            if ($cat === null) {
                continue;
            }
            $categories[$i] = [
                'id' => (int) $cat['rowid'],
                'fk_parent' => (int) $cat['fk_parent'],
                'label' => $cat['label'],
                'description' => $cat['description'],
                'color' => $cat['color'],
                'position' => (int) $cat['position'],
                'socid' => (int) $cat['fk_soc'],
                'ref_ext' => $cat['ref_ext'],
                'visible' => (int) $cat['visible'],
                'type' => (int) $cat['type'],
                'entity' => (int) $cat['entity'],
                'array_options' => $this->extrafieldsAsArrayOptions((int) $cat['rowid']),
            ];
        }

        return $categories;
    }

    /**
     * Port of Categorie::getObjectsInCateg($type, $onlyids): objects linked to
     * this category (entity-scoped join on the object table).
     *
     * @return array<int, mixed>|int list of object ids (onlyids) or raw object
     *         rows, -1 on error
     */
    public function getObjectsInCateg(int $catId, string $type, int $onlyids = 0): array|int
    {
        $this->resetErrors();

        $fk = self::MAP_CAT_FK[$type] ?? $type;
        $catTable = 'llx_categorie_' . (self::MAP_CAT_TABLE[$type] ?? $type);
        $objTable = 'llx_' . (self::MAP_OBJ_TABLE[$type] ?? $type);
        $element = self::MAP_OBJ_ELEMENT[$type] ?? $type;

        $sql = 'SELECT c.fk_' . $this->utils->sanitizeIdentifier($fk) . ' as fk_object';
        $sql .= ' FROM ' . $this->utils->sanitizeIdentifier($catTable) . ' as c';
        $sql .= ', ' . $this->utils->sanitizeIdentifier($objTable) . ' as o';
        $sql .= ' WHERE o.entity IN (' . $this->config->getEntity($element) . ')';
        $sql .= ' AND c.fk_categorie = ' . (int) $catId;
        // actioncomm uses id instead of rowid upstream
        $mappedTable = $this->mappedObjTable($type);
        if (($mappedTable !== null && $mappedTable === 'actioncomm') || $type === 'actioncomm') {
            $sql .= ' AND c.fk_' . $this->utils->sanitizeIdentifier($fk) . ' = o.id';
        } else {
            $sql .= ' AND c.fk_' . $this->utils->sanitizeIdentifier($fk) . ' = o.rowid';
        }

        try {
            $objectIds = array_map('intval', $this->db->fetchFirstColumn($sql));
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        if ($onlyids) {
            return $objectIds;
        }

        $objs = [];
        foreach ($objectIds as $objId) {
            $obj = $this->fetchObjectRow($type, $objId);
            if ($obj !== null) {
                $objs[] = $obj;
            }
        }

        return $objs;
    }

    /**
     * Port of Categorie::already_exists(): same label + same parent + same
     * type within the entity scope (excluding the current row id).
     *
     * @param array{id:int,type:int,fk_parent:int,label:string} $cat
     * @return int 1 exists, 0 not, -1 error
     */
    public function alreadyExists(array $cat): int
    {
        $type = $cat['type'];
        if (!is_numeric($type)) {
            $type = self::MAP_ID[(string) $type] ?? null;
        }

        try {
            $rowid = $this->db->fetchOne(
                'SELECT c.rowid FROM llx_categorie as c'
                . ' WHERE c.entity IN (' . $this->config->getEntity('category') . ')'
                . ' AND c.type = ' . ((int) $type)
                . ' AND c.fk_parent = ' . ((int) $cat['fk_parent'])
                . ' AND c.label = ' . $this->db->quote((string) $cat['label']),
            );
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return -1;
        }

        if ($rowid !== false && (int) $rowid > 0 && (int) $rowid !== (int) $cat['id']) {
            return 1;
        }

        return 0;
    }

    /**
     * Fetch the object a link/unlink endpoint targets, by id.
     * Replicates $object->fetch($object_id) per type:
     *  - customer/supplier: Societe::fetch — entity-filtered (getEntity('societe'))
     *  - contact: Contact::fetch — no entity filter on the rowid path upstream
     *
     * @return array<string, mixed>|null
     */
    public function fetchObjectById(string $type, int $id): ?array
    {
        try {
            switch ($type) {
                case 'customer':
                case 'supplier':
                    $row = $this->db->fetchAssociative(
                        'SELECT s.* FROM llx_societe s'
                        . ' WHERE s.entity IN (' . $this->config->getEntity('societe') . ') AND s.rowid = ?',
                        [$id],
                    );

                    return $row === false ? null : $row;
                case 'contact':
                    $row = $this->db->fetchAssociative('SELECT c.* FROM llx_socpeople c WHERE c.rowid = ?', [$id]);

                    return $row === false ? null : $row;
                case 'product':
                case 'member':
                case 'actioncomm':
                case 'project':
                    $table = 'llx_' . (self::MAP_OBJ_TABLE[$type] ?? $type);
                    $pk = $type === 'actioncomm' ? 'id' : 'rowid';
                    $row = $this->db->fetchAssociative(
                        'SELECT * FROM ' . $this->utils->sanitizeIdentifier($table) . ' WHERE ' . $pk . ' = ?',
                        [$id],
                    );

                    return $row === false ? null : $row;
                default:
                    return null;
            }
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return null;
        }
    }

    /**
     * Fetch a linkable object by ref — $object->fetch(0, $ref) upstream:
     *  - customer/supplier (Societe): matches s.nom, entity-filtered
     *  - contact: upstream Contact::fetch(0, $ref) puts $ref in the $user
     *    argument and always fails with 'Bad parameters' — replicated.
     *
     * @return array<string, mixed>|null
     */
    public function fetchObjectByRef(string $type, string $ref): ?array
    {
        try {
            switch ($type) {
                case 'customer':
                case 'supplier':
                    $row = $this->db->fetchAssociative(
                        'SELECT s.* FROM llx_societe s'
                        . ' WHERE s.entity IN (' . $this->config->getEntity('societe') . ') AND s.nom = ?',
                        [$ref],
                    );

                    return $row === false ? null : $row;
                case 'contact':
                    $this->fail('Bad parameters');

                    return null;
                case 'product':
                case 'member':
                case 'actioncomm':
                    $table = 'llx_' . (self::MAP_OBJ_TABLE[$type] ?? $type);
                    $row = $this->db->fetchAssociative(
                        'SELECT * FROM ' . $this->utils->sanitizeIdentifier($table)
                        . ' WHERE ref = ' . $this->db->quote($ref),
                    );

                    return $row === false ? null : $row;
                default:
                    return null;
            }
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return null;
        }
    }

    /**
     * Port of _checkAccessToLinkedObject(): entity check on the linked object
     * (societe for customer/supplier; socpeople-or-parent-societe for contact).
     */
    public function checkAccessToLinkedObject(string $type, array $object): bool
    {
        $objectId = (int) ($object['rowid'] ?? $object['id'] ?? 0);

        try {
            switch ($type) {
                case 'customer':
                case 'supplier':
                    $row = $this->db->fetchOne(
                        'SELECT rowid FROM llx_societe'
                        . ' WHERE rowid = ? AND entity IN (' . $this->config->getEntity('societe') . ')',
                        [$objectId],
                    );

                    return $row !== false;
                case 'contact':
                    $row = $this->db->fetchOne(
                        'SELECT rowid FROM llx_socpeople WHERE rowid = ?'
                        . ' AND (entity IN (' . $this->config->getEntity('socpeople') . ')'
                        . ' OR fk_soc IN (SELECT rowid FROM llx_societe'
                        . ' WHERE entity IN (' . $this->config->getEntity('societe') . ')))',
                        [$objectId],
                    );

                    return $row !== false;
                default:
                    return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether a category row is visible in the current entity scope —
     * _checkAccessToResource('categorie', id) reduces to an entity check for
     * the internal API user.
     *
     * @param array<string, mixed> $cat
     */
    public function isInEntityScope(array $cat): bool
    {
        $entities = array_map('intval', explode(',', $this->config->getEntity('category')));

        return in_array((int) $cat['entity'], $entities, true);
    }

    /**
     * Fetch raw extrafields of a category (llx_categories_extrafields row
     * minus internal columns, keyed by bare column name).
     *
     * @return array<string, mixed>
     */
    public function fetchExtrafields(int $catId): array
    {
        try {
            $row = $this->db->fetchAssociative(
                'SELECT * FROM llx_categories_extrafields WHERE fk_object = ?',
                [$catId]
            );
        } catch (\Throwable) {
            $row = false;
        }
        if ($row === false) {
            return [];
        }
        foreach (self::EXTRAFIELDS_INTERNAL_COLUMNS as $internal) {
            unset($row[$internal]);
        }

        return $row;
    }

    /**
     * Extrafields in upstream array_options shape: keys prefixed 'options_'.
     *
     * @return array<string, mixed>
     */
    public function extrafieldsAsArrayOptions(int $catId): array
    {
        $options = [];
        foreach ($this->fetchExtrafields($catId) as $col => $value) {
            $options['options_' . $col] = $value;
        }

        return $options;
    }

    /**
     * Port of CommonObject::insertExtraFields()/deleteExtraFields() for
     * categories: delete the llx_categories_extrafields row, re-insert the
     * provided values ('options_x' keys map to column 'x'; only real columns
     * are written, like upstream's attribute filter).
     *
     * @param array<string, mixed> $arrayOptions
     */
    public function saveExtrafields(int $catId, array $arrayOptions): void
    {
        $columns = array_map(
            static fn ($column): string => $column->getName(),
            $this->db->createSchemaManager()->listTableColumns('llx_categories_extrafields'),
        );
        $extraColumns = array_diff($columns, self::EXTRAFIELDS_INTERNAL_COLUMNS);

        $values = [];
        foreach ($arrayOptions as $key => $value) {
            $col = str_starts_with((string) $key, 'options_') ? substr((string) $key, 8) : (string) $key;
            if (in_array($col, $extraColumns, true)) {
                $values[$col] = $value;
            }
        }

        $this->db->delete('llx_categories_extrafields', ['fk_object' => $catId]);
        if ($values !== []) {
            $this->db->insert('llx_categories_extrafields', ['fk_object' => $catId] + $values);
        }
    }

    /**
     * Raw object row used by getObjects: join-filtered fetch by pk.
     *
     * @return array<string, mixed>|null
     */
    private function fetchObjectRow(string $type, int $id): ?array
    {
        $objTable = 'llx_' . (self::MAP_OBJ_TABLE[$type] ?? $type);
        $pk = $type === 'actioncomm' ? 'id' : 'rowid';

        try {
            $row = $this->db->fetchAssociative(
                'SELECT o.* FROM ' . $this->utils->sanitizeIdentifier($objTable) . ' o WHERE o.' . $pk . ' = ?',
                [$id],
            );
        } catch (\Throwable) {
            return null;
        }

        return $row === false ? null : $row;
    }

    /** Port of $db->order(). */
    public function orderBy(string $sortfield, string $sortorder): string
    {
        if ($sortfield === '') {
            return '';
        }
        $sortfield = (string) preg_replace('/[a-z_]+\([^\)]*\) as ([\w]+)/i', '\1', $sortfield);
        $fields = explode(',', $sortfield);
        $orders = $sortorder !== '' ? explode(',', $sortorder) : [];
        $return = '';
        $oldsortorder = '';
        $i = 0;
        foreach ($fields as $val) {
            $fieldname = (string) preg_replace('/[^0-9a-z_\.]/i', '', $val);
            if (!$fieldname) {
                continue;
            }
            $return .= $return ? ', ' : ' ORDER BY ';
            $return .= $fieldname;
            $tmpsortorder = empty($orders[$i]) ? '' : trim($orders[$i]);
            if (strtoupper($tmpsortorder) === 'ASC') {
                $oldsortorder = 'ASC';
                $return .= ' ASC';
            } elseif (strtoupper($tmpsortorder) === 'DESC') {
                $oldsortorder = 'DESC';
                $return .= ' DESC';
            } else {
                $return .= ' ' . ($oldsortorder ?: 'ASC');
            }
            $i++;
        }

        return $return;
    }
}
