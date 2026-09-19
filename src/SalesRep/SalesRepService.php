<?php

declare(strict_types=1);

namespace App\SalesRep;

use App\Aux\DolibarrContext;
use Doctrine\DBAL\Connection;

/**
 * Port of the sales-representative part of Societe
 * (htdocs/societe/class/societe.class.php): getSalesRepresentatives,
 * add_commercial, del_commercial — plus getSalesRepresentativeSqlFilter
 * (htdocs/core/lib/company.lib.php).
 *
 * fk_user stays a soft integer reference to the llx_user stub — no FK, same
 * as upstream.
 */
final class SalesRepService
{
    /** Columns read by upstream getSalesRepresentatives() on llx_user. */
    private const USER_COLUMNS = [
        'gender', 'job', 'office_phone', 'office_fax', 'user_mobile',
        'personal_mobile', 'email', 'photo', 'statut',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrContext $config,
    ) {
    }

    /**
     * Port of Societe::getSalesRepresentatives().
     *
     * @return int|array<int, int>|array<int, array<string, mixed>> -1 on SQL
     *         error, array of user ids when $mode != 0, array of rep rows
     *         otherwise
     */
    public function getSalesRepresentatives(
        int $socid,
        int $mode = 0,
        ?string $sortfield = null,
        ?string $sortorder = null,
    ): int|array {
        $userCols = $this->availableUserColumns();

        $sql = 'SELECT u.rowid, u.login, u.lastname, u.firstname';
        $sql .= in_array('office_phone', $userCols, true) ? ', u.office_phone' : ', null as office_phone';
        $sql .= in_array('job', $userCols, true) ? ', u.job' : ', null as job';
        $sql .= in_array('email', $userCols, true) ? ', u.email' : ', null as email';
        $sql .= in_array('statut', $userCols, true) ? ', u.statut as status' : ', null as status';
        $sql .= ', u.entity';
        $sql .= in_array('photo', $userCols, true) ? ', u.photo' : ', null as photo';
        $sql .= in_array('gender', $userCols, true) ? ', u.gender' : ', null as gender';
        $sql .= in_array('office_fax', $userCols, true) ? ', u.office_fax' : ', null as office_fax';
        $sql .= in_array('user_mobile', $userCols, true) ? ', u.user_mobile' : ', null as user_mobile';
        $sql .= in_array('personal_mobile', $userCols, true) ? ', u.personal_mobile' : ', null as personal_mobile';
        $sql .= ' FROM llx_societe_commerciaux as sc, llx_user as u';
        // Condition here should be the same than into select_dolusers()
        if ($this->config->isModEnabled('multicompany') && $this->config->getBool('MULTICOMPANY_TRANSVERSE_MODE')) {
            $sql .= ' WHERE u.rowid IN (SELECT ug.fk_user FROM llx_usergroup_user as ug'
                . ' WHERE ug.entity IN (' . $this->config->getEntity('usergroup') . '))';
        } else {
            $sql .= ' WHERE u.entity IN (0, ' . ((int) $this->config->entity()) . ')';
        }
        $sql .= ' AND u.rowid = sc.fk_user AND sc.fk_soc = ' . (int) $socid;
        if (empty($sortfield)) {
            $sortfield = 'u.lastname,u.firstname';
        }
        if (empty($sortorder)) {
            $sortorder = str_repeat('ASC,', count(explode(',', $sortfield)) - 1) . 'ASC';
        }
        $sql .= $this->orderBy($sortfield, $sortorder);

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable) {
            return -1;
        }

        $reparray = [];
        foreach ($rows as $obj) {
            if (empty($mode)) {
                $reparray[] = [
                    'id' => (int) $obj['rowid'],
                    'lastname' => $obj['lastname'],
                    'firstname' => $obj['firstname'],
                    'email' => $obj['email'],
                    'phone' => $obj['office_phone'],
                    'office_phone' => $obj['office_phone'],
                    'office_fax' => $obj['office_fax'],
                    'user_mobile' => $obj['user_mobile'],
                    'personal_mobile' => $obj['personal_mobile'],
                    'job' => $obj['job'],
                    'statut' => $obj['status'],
                    'status' => $obj['status'],
                    'entity' => (int) $obj['entity'],
                    'login' => $obj['login'],
                    'photo' => $obj['photo'],
                    'gender' => $obj['gender'],
                ];
            } else {
                $reparray[] = (int) $obj['rowid'];
            }
        }

        return $reparray;
    }

    /**
     * Port of Societe::add_commercial(): delete any existing link then insert,
     * inside a transaction.
     *
     * @return int positive on success, 0 when ids are not positive, negative on failure
     */
    public function addCommercial(int $socid, int $commid): int
    {
        if ($socid <= 0 || $commid <= 0) {
            return 0;
        }

        $error = 0;
        $this->db->beginTransaction();
        try {
            $this->db->executeStatement(
                'DELETE FROM llx_societe_commerciaux WHERE fk_soc = ? AND fk_user = ?',
                [$socid, $commid],
            );
            $this->db->executeStatement(
                'INSERT INTO llx_societe_commerciaux (fk_soc, fk_user) VALUES (?, ?)',
                [$socid, $commid],
            );
        } catch (\Throwable) {
            $error++;
        }

        if (!$error) {
            $this->db->commit();

            return 1;
        }
        $this->db->rollBack();

        return -1;
    }

    /**
     * Port of Societe::del_commercial(). Upstream always returns 1 when the
     * delete did not fail (ids <= 0 skip the delete entirely).
     *
     * @return int negative on failure, positive on success
     */
    public function delCommercial(int $socid, int $commid): int
    {
        $error = 0;

        if ($socid > 0 && $commid > 0) {
            try {
                $this->db->executeStatement(
                    'DELETE FROM llx_societe_commerciaux WHERE fk_soc = ? AND fk_user = ?',
                    [$socid, $commid],
                );
            } catch (\Throwable) {
                $error++;
            }
        }

        return $error ? -1 : 1;
    }

    /**
     * Existence check equivalent to User::fetch($id) — upstream filters on
     * u.rowid only when fetching by id.
     */
    public function userExists(int $userId): bool
    {
        return (bool) $this->db->fetchOne('SELECT rowid FROM llx_user WHERE rowid = ?', [$userId]);
    }

    /**
     * Row of llx_societe for the api's _fetch() equivalent: entity-scoped,
     * with 'id' aliasing rowid like the cleaned Societe object upstream.
     *
     * @return array<string, mixed>|null
     */
    public function fetchSociete(int $socid): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM llx_societe WHERE rowid = ? AND entity IN (' . $this->config->getEntity('societe') . ')',
            [$socid],
        );
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['rowid'];

        return $row;
    }

    /** True when the third party exists inside the current entity scope. */
    public function thirdpartyExists(int $socid): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT rowid FROM llx_societe WHERE rowid = ? AND entity IN (' . $this->config->getEntity('societe') . ')',
            [$socid],
        );
    }

    /**
     * Port of getSalesRepresentativeSqlFilter() (company.lib.php): the
     * "EXISTS(...)"/"NOT EXISTS(...)" fragment on llx_societe_commerciaux used
     * to restrict thirdparty lists to reps' customers.
     *
     * @param int|string|int[] $userIds
     */
    public function salesRepresentativeSqlFilter(
        string $socidField,
        int|string|array $userIds = 0,
        int $not = 0,
    ): string {
        if (!is_array($userIds)) {
            $userIds = $userIds === '' ? [] : explode(',', (string) $userIds);
        }
        $userIds = array_values(array_filter(array_map('intval', $userIds), static function (int $v): bool {
            return $v > 0;
        }));

        $sql = ($not ? 'NOT EXISTS' : 'EXISTS');
        $sql .= ' (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc'
            . ' WHERE ' . $this->sanitize($socidField) . ' = sc.fk_soc';
        if (!empty($userIds)) {
            $sql .= ' AND sc.fk_user IN (' . $this->sanitize(implode(',', $userIds)) . ')';
        }
        $sql .= ')';

        return $sql;
    }

    /**
     * Columns of the local llx_user stub that actually exist — the stub is
     * intentionally minimal, so upstream-only columns are emitted as null.
     *
     * @return string[]
     */
    private function availableUserColumns(): array
    {
        try {
            $cols = $this->db->createSchemaManager()->listTableColumns('llx_user');
        } catch (\Throwable) {
            return [];
        }

        return array_intersect(self::USER_COLUMNS, array_keys($cols));
    }

    /**
     * Port of DoliDB::order(): " ORDER BY <sortfield> <sortorder>" with
     * field/keyword sanitization.
     */
    private function orderBy(string $sortfield, string $sortorder): string
    {
        $fields = array_values(array_filter(array_map('trim', explode(',', $this->sanitize($sortfield)))));
        $orders = array_map('trim', explode(',', $this->sanitize($sortorder)));
        if (empty($fields)) {
            return '';
        }

        $out = ' ORDER BY ';
        foreach ($fields as $i => $field) {
            if ($i > 0) {
                $out .= ', ';
            }
            $order = strtoupper($orders[$i] ?? 'ASC');
            $out .= $field . ' ' . ($order === 'DESC' ? 'DESC' : 'ASC');
        }

        return $out;
    }

    /** Port of DoliDB::sanitize() — strips characters outside [a-z0-9_,.\s]. */
    private function sanitize(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_.,\s]/', '', $value);
    }
}
