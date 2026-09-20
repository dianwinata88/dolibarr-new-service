<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of the parts of Categorie (htdocs/categories/class/categorie.class.php)
 * used by the third-party API: containing(), getListForItem(),
 * add_type()/del_type() and setCategoriesCommon().
 */
final class CategoryService
{
    /** Upstream MAP_ID for the types reachable from this API. */
    private const MAP_ID = [
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

    private const MAP_CAT_FK = [
        'customer' => 'soc',
        'supplier' => 'soc',
        'contact' => 'socpeople',
        'bank_account' => 'account',
    ];

    private const MAP_CAT_TABLE = [
        'customer' => 'societe',
        'supplier' => 'fournisseur',
        'bank_account' => 'account',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
    ) {
    }

    /**
     * Port of Categorie::containing($id, $type, $mode).
     *
     * @return array<int, mixed>|int ids when $mode='id'
     */
    public function containing(int $id, string $type, string $mode = 'object'): array|int
    {
        $cats = [];

        if (is_numeric($type)) {
            $type = (string) array_search((int) $type, self::MAP_ID, true);
        }

        $table = self::MAP_CAT_TABLE[$type] ?? $type;
        $fk = self::MAP_CAT_FK[$type] ?? $type;

        $sql = "SELECT ct.fk_categorie, c.label, c.rowid"
            . " FROM llx_categorie_" . $this->utils->sanitizeIdentifier($table) . " as ct, llx_categorie as c"
            . " WHERE ct.fk_categorie = c.rowid AND ct.fk_" . $this->utils->sanitizeIdentifier($fk) . " = " . (int) $id
            . " AND c.entity IN (" . $this->config->getEntity('category') . ")";

        $rows = $this->db->fetchAllAssociative($sql);
        foreach ($rows as $obj) {
            if ($mode === 'id') {
                $cats[] = (int) $obj['fk_categorie'];
            } elseif ($mode === 'label') {
                $cats[] = $obj['label'];
            } else {
                $cat = $this->fetch((int) $obj['fk_categorie']);
                if ($cat !== null) {
                    $cats[] = $cat;
                }
            }
        }

        return $cats;
    }

    /**
     * Port of Categorie::getListForItem().
     *
     * @return array<int, array<string, mixed>>|int -1 on SQL error
     */
    public function getListForItem(int $id, string $type = 'customer', string $sortfield = 's.rowid', string $sortorder = 'ASC', int $limit = 0, int $page = 0): array|int
    {
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

        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;
            $sql .= $this->orderBy($sortfield, $sortorder);
            $sql .= ' LIMIT ' . ($limit + 1) . ' OFFSET ' . $offset;
        } else {
            $sql .= $this->orderBy($sortfield, $sortorder);
        }

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return -1;
        }

        $num = count($rows);
        $min = min($num, ($limit <= 0 ? $num : $limit));

        $categories = [];
        for ($i = 0; $i < $min; $i++) {
            $cat = $this->fetch((int) $rows[$i]['rowid']);
            if ($cat === null) {
                continue;
            }
            $categories[$i] = [
                'id' => $cat['rowid'],
                'fk_parent' => $cat['fk_parent'],
                'label' => $cat['label'],
                'description' => $cat['description'],
                'color' => $cat['color'],
                'position' => $cat['position'],
                'socid' => $cat['fk_soc'],
                'ref_ext' => $cat['ref_ext'],
                'visible' => $cat['visible'],
                'type' => $cat['type'],
                'entity' => $cat['entity'],
                'array_options' => $this->fetchExtrafields((int) $cat['rowid']),
            ];
        }

        return $categories;
    }

    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Port of Categorie::add_type().
     *
     * @return int 1 OK, -1/-2/-3 KO
     */
    public function addType(int $catId, int $objId, string $type): int
    {
        if ($catId === -1) {
            return -2;
        }

        $table = 'llx_categorie_' . (self::MAP_CAT_TABLE[$type] ?? $type);
        $fk = self::MAP_CAT_FK[$type] ?? $type;

        try {
            $this->db->insert($table, ['fk_categorie' => $catId, 'fk_' . $fk => $objId]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '1062')) {
                $this->lastError = 'DB_ERROR_RECORD_ALREADY_EXISTS';

                return -3;
            }
            $this->lastError = $e->getMessage();

            return -1;
        }

        return 1;
    }

    /**
     * Port of Categorie::del_type().
     */
    public function delType(int $catId, int $objId, string $type): int
    {
        if ($type === 'societe') {
            $type = 'customer';
        } elseif ($type === 'fournisseur') {
            $type = 'supplier';
        }

        $table = 'llx_categorie_' . (self::MAP_CAT_TABLE[$type] ?? $type);
        $fk = self::MAP_CAT_FK[$type] ?? $type;

        try {
            $this->db->executeStatement(
                "DELETE FROM $table WHERE fk_categorie = " . (int) $catId . " AND fk_" . $fk . " = " . (int) $objId,
            );
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return -1;
        }

        return 1;
    }

    /**
     * Port of CommonObject::setCategoriesCommon(): diff add/del links.
     *
     * @param int[] $cats
     */
    public function setCategories(array $cats, string $type, int $objId): int
    {
        $existing = array_map('intval', $this->containing($objId, $type, 'id'));
        $wanted = array_map('intval', $cats);

        foreach (array_diff($wanted, $existing) as $catId) {
            $this->addType($catId, $objId, $type);
        }
        foreach (array_diff($existing, $wanted) as $catId) {
            $this->delType($catId, $objId, $type);
        }

        return 1;
    }

    /**
     * Fetch a category row.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $id): ?array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM llx_categorie WHERE rowid = ?', [$id]);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed> */
    private function fetchExtrafields(int $catId): array
    {
        try {
            $row = $this->db->fetchAssociative('SELECT * FROM llx_categorie_extrafields WHERE fk_object = ?', [$catId]);
        } catch (\Throwable) {
            $row = false; // llx_categorie_extrafields may not exist in this schema
        }
        if ($row === false) {
            return [];
        }
        unset($row['rowid'], $row['tms'], $row['fk_object'], $row['import_key']);

        return $row;
    }

    /** Port of $db->order() */
    private function orderBy(string $sortfield, string $sortorder): string
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
