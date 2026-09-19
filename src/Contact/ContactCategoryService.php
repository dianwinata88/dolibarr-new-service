<?php

declare(strict_types=1);

namespace App\Contact;

use Doctrine\DBAL\Connection;

/**
 * Port of the parts of Categorie (htdocs/categories/class/categorie.class.php)
 * used by the contacts API: getListForItem(..., 'contact'), add_type() and
 * del_type() on llx_categorie_contact (MAP_ID contact = 4, fk = socpeople).
 */
final class ContactCategoryService
{
    private ?string $lastError = null;

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Port of Categorie::getListForItem($id, 'contact', ...).
     *
     * @return array<int, array<string, mixed>>|int -1 on SQL error
     */
    public function getListForItem(int $id, string $sortfield = 's.rowid', string $sortorder = 'ASC', int $limit = 0, int $page = 0): array|int
    {
        $sql = 'SELECT s.rowid';
        $sql .= ' FROM llx_categorie as s, llx_categorie_contact as sub';
        $sql .= ' WHERE s.entity IN (' . $this->config->getEntity('category') . ')';
        $sql .= ' AND s.type = 4';
        $sql .= ' AND s.rowid = sub.fk_categorie';
        $sql .= ' AND sub.fk_socpeople = ' . (int) $id;
        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $sql .= ' LIMIT ' . ($limit + 1) . ' OFFSET ' . ($limit * $page);
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
                'id' => (int) $cat['rowid'],
                'fk_parent' => (int) $cat['fk_parent'],
                'label' => $cat['label'],
                'description' => $cat['description'],
                'color' => $cat['color'],
                'position' => $cat['position'] === null ? null : (int) $cat['position'],
                'socid' => $cat['fk_soc'] === null ? null : (int) $cat['fk_soc'],
                'ref_ext' => $cat['ref_ext'],
                'visible' => (int) $cat['visible'],
                'type' => (int) $cat['type'],
                'entity' => (int) $cat['entity'],
                'array_options' => $this->fetchExtrafields((int) $cat['rowid']),
            ];
        }

        return $categories;
    }

    /**
     * Port of Categorie::add_type() on the 'contact' table, including the
     * CATEGORIE_RECURSIV_ADD parent-chain replication.
     *
     * @return int 1 OK, -1/-2/-3 KO (upstream codes)
     */
    public function addType(int $catId, int $contactId): int
    {
        if ($catId === -1) {
            return -2;
        }

        try {
            $this->db->insert('llx_categorie_contact', [
                'fk_categorie' => $catId,
                'fk_socpeople' => $contactId,
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '1062')) {
                $this->lastError = 'DB_ERROR_RECORD_ALREADY_EXISTS';

                return -3;
            }
            $this->lastError = $e->getMessage();

            return -1;
        }

        if ($this->config->getString('CATEGORIE_RECURSIV_ADD')) {
            $parent = $this->db->fetchOne('SELECT fk_parent FROM llx_categorie WHERE rowid = ' . ((int) $catId));
            if ($parent !== false && !empty($parent)) {
                $already = $this->db->fetchOne(
                    'SELECT fk_categorie FROM llx_categorie_contact WHERE fk_categorie = ' . ((int) $parent) . ' AND fk_socpeople = ' . ((int) $contactId)
                );
                if ($already === false) {
                    return $this->addType((int) $parent, $contactId);
                }
            }
        }

        return 1;
    }

    /**
     * Port of Categorie::del_type() on the 'contact' table.
     */
    public function delType(int $catId, int $contactId): int
    {
        try {
            $this->db->executeStatement(
                'DELETE FROM llx_categorie_contact WHERE fk_categorie = ' . ((int) $catId) . ' AND fk_socpeople = ' . ((int) $contactId)
            );
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return -1;
        }

        return 1;
    }

    /**
     * Fetch a category row (Categorie::fetch() columns used by the API).
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
            $row = $this->db->fetchAssociative('SELECT * FROM llx_categories_extrafields WHERE fk_object = ?', [$catId]);
        } catch (\Throwable) {
            $row = false;
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
            if ($fieldname === '') {
                continue;
            }
            $return .= $return === '' ? ' ORDER BY ' : ', ';
            $return .= $fieldname;
            $tmpsortorder = empty($orders[$i]) ? '' : trim($orders[$i]);
            if (strtoupper($tmpsortorder) === 'ASC') {
                $oldsortorder = 'ASC';
                $return .= ' ASC';
            } elseif (strtoupper($tmpsortorder) === 'DESC') {
                $oldsortorder = 'DESC';
                $return .= ' DESC';
            } else {
                $return .= ' ' . ($oldsortorder !== '' ? $oldsortorder : 'ASC');
            }
            $i++;
        }

        return $return;
    }
}
