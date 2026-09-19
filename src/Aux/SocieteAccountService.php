<?php

declare(strict_types=1);

namespace App\Aux;

use Doctrine\DBAL\Connection;

/**
 * Port of SocieteAccount (htdocs/societe/class/societeaccount.class.php):
 * the llx_societe_account persistence behind the thirdparty accounts API.
 *
 * Data is exchanged as associative arrays keyed by the llx_societe_account
 * column names (plus 'id' as alias of rowid), mirroring the SocieteAccount
 * object fields returned by the upstream API.
 */
final class SocieteAccountService
{
    /**
     * Columns accepted from API input (upstream SocieteAccount::$fields minus
     * rowid/entity/tms which are managed internally).
     */
    public const FIELDS = [
        'login', 'pass_encoding', 'pass_crypted', 'pass_temp',
        'fk_soc', 'site', 'fk_website', 'site_account', 'key_account',
        'date_last_login', 'date_previous_login', 'date_last_reset_password',
        'note_private', 'date_creation', 'fk_user_creat', 'fk_user_modif',
        'import_key', 'status',
    ];

    /** Columns upstream declares notnull=1 — inserted as '' / fallback, never NULL. */
    private const NOTNULL_STRING_FIELDS = ['login', 'pass_crypted', 'site', 'date_creation'];

    /** Columns cast to int on assignment (upstream _checkValForAPI 'int' check). */
    private const INT_FIELDS = ['entity', 'fk_soc', 'fk_website', 'fk_user_creat', 'fk_user_modif', 'status'];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrContext $config,
    ) {
    }

    /**
     * Filter an input payload to writable columns and cast values like
     * DolibarrApi::_checkValForAPI(). The 'caller' key is dropped by the
     * caller (it only feeds the upstream trigger context).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitizeInput(array $data): array
    {
        $out = [];
        foreach ($data as $field => $value) {
            if ($field === 'caller') {
                continue;
            }
            if (!in_array($field, self::FIELDS, true) && $field !== 'entity') {
                continue; // unknown property: upstream assigns it dynamically, it just never reaches the db
            }
            if (in_array($field, self::INT_FIELDS, true)) {
                $out[$field] = ($value === null || $value === '') ? null : (int) $value;
            } else {
                $out[$field] = $value === null ? null : (is_scalar($value) ? (string) $value : json_encode($value));
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null full row (plus 'id') or null */
    public function fetch(int $rowid): ?array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM llx_societe_account WHERE rowid = ?', [$rowid]);
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['rowid'];

        return $row;
    }

    /**
     * First account matching (fk_soc, site) — same query the endpoints run.
     *
     * @return array<string, mixed>|null
     */
    public function fetchBySocAndSite(int $socid, string $site): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT rowid, fk_user_creat, date_creation FROM llx_societe_account WHERE fk_soc = ? AND site = ?',
            [$socid, $site],
        );
        if ($row === false) {
            return null;
        }

        return $this->fetch((int) $row['rowid']);
    }

    /** @return array<int, array<string, mixed>> rows matching fk_soc (+ optional site) */
    public function findBySoc(int $socid, ?string $site = null): array
    {
        $sql = 'SELECT rowid, fk_soc, key_account, site, date_creation, tms FROM llx_societe_account WHERE fk_soc = ?';
        $params = [$socid];
        if ($site !== null && $site !== '') {
            $sql .= ' AND site = ?';
            $params[] = $site;
        }

        return $this->db->fetchAllAssociative($sql, $params);
    }

    /** @return array<int, array<string, mixed>> */
    public function findBySiteAndKeyAccount(string $site, string $keyAccount): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT rowid, fk_soc, key_account, site, date_creation, tms FROM llx_societe_account'
            . " WHERE site = ? AND key_account = ? AND entity IN (" . $this->config->getEntity('societe') . ')',
            [$site, $keyAccount],
        );
    }

    /**
     * Port of SocieteAccount::create() + createCommon().
     *
     * @param array<string, mixed> $data sanitized field map (fk_soc and site set)
     * @return int >0 = new rowid, -1 = ko (e.g. dolibarr_website without fk_website), -2 = duplicate portal login
     */
    public function create(array $data): int
    {
        $site = (string) ($data['site'] ?? '');
        if ($site === 'dolibarr_website' && (int) ($data['fk_website'] ?? 0) <= 0) {
            return -1;
        }
        if ($site === 'dolibarr_portal') {
            $dup = $this->db->fetchOne(
                'SELECT sa.rowid FROM llx_societe_account as sa WHERE sa.login = ?'
                . " AND sa.entity IN (" . $this->config->getEntity('societe') . ") AND sa.site = 'dolibarr_portal'"
                . ' ORDER BY sa.rowid DESC',
                [(string) ($data['login'] ?? '')],
            );
            if ($dup !== false && $dup !== null) {
                return -2;
            }
        }

        $fields = $data;
        $fields['entity'] = (int) ($data['entity'] ?? $this->config->entity());
        if (empty($fields['date_creation'])) {
            $fields['date_creation'] = date('Y-m-d H:i:s');
        }
        if (empty($fields['fk_user_creat']) || (int) $fields['fk_user_creat'] <= 0) {
            $fields['fk_user_creat'] = $this->config->apiUserId();
        }
        if (!isset($fields['status'])) {
            $fields['status'] = 1;
        }
        foreach (self::NOTNULL_STRING_FIELDS as $f) {
            if (!isset($fields[$f])) {
                $fields[$f] = '';
            }
        }

        try {
            $this->db->insert('llx_societe_account', $fields);
        } catch (\Throwable) {
            return -1;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Port of SocieteAccount::update() + updateCommon(): writes every declared
     * field (full replace of the column set), stamps fk_user_modif.
     * With $rowid <= 0 the UPDATE matches nothing and still reports success —
     * same as upstream updateCommon.
     *
     * @param array<string, mixed> $data full field map
     * @return int negative on failure
     */
    public function update(int $rowid, array $data): int
    {
        $fields = [];
        foreach (self::FIELDS as $f) {
            $fields[$f] = $data[$f] ?? null;
        }
        if (isset($data['entity'])) {
            $fields['entity'] = (int) $data['entity'];
        }
        foreach (self::NOTNULL_STRING_FIELDS as $f) {
            if ($fields[$f] === null) {
                $fields[$f] = '';
            }
        }
        if ($fields['status'] === null) {
            $fields['status'] = 1;
        }
        if ($this->config->apiUserId() > 0) {
            $fields['fk_user_modif'] = $this->config->apiUserId();
        }

        try {
            $this->db->update('llx_societe_account', $fields, ['rowid' => $rowid]);
        } catch (\Throwable) {
            return -1;
        }

        return 1;
    }

    /** Port of SocieteAccount::delete(). @return int <0 if KO */
    public function delete(int $rowid): int
    {
        try {
            $this->db->delete('llx_societe_account', ['rowid' => $rowid]);
        } catch (\Throwable) {
            return -1;
        }

        return 1;
    }

    /**
     * Port of SocieteAccount::getCustomerAccount(): external customer key of a
     * third party for a given site ('' when none).
     */
    public function getCustomerAccount(
        int $id,
        string $site,
        int $status = 0,
        string $siteAccount = '',
        int $fkWebsite = 0,
    ): string {
        $sql = 'SELECT sa.key_account as key_account, sa.entity FROM llx_societe_account as sa'
            . ' WHERE sa.fk_soc = ?'
            . " AND sa.entity IN (" . $this->config->getEntity('societe') . ')'
            . ' AND sa.site = ?';
        $params = [$id, $site];
        if ($fkWebsite > 0) {
            $sql .= ' AND sa.fk_website = ?';
            $params[] = $fkWebsite;
        }
        if ($status >= 0) {
            $sql .= ' AND sa.status = ?';
            $params[] = $status;
        }
        $sql .= " AND sa.key_account IS NOT NULL AND sa.key_account <> ''";
        $sql .= " AND (sa.site_account = '' OR sa.site_account IS NULL OR sa.site_account = ?)";
        $params[] = $siteAccount;
        $sql .= ' ORDER BY sa.site_account DESC, sa.rowid DESC'; // entry with a site_account defined first

        $key = $this->db->fetchOne($sql, $params);

        return $key === false || $key === null ? '' : (string) $key;
    }

    /**
     * Port of SocieteAccount::getThirdPartyID(): thirdparty id from an
     * external account key (0 when none).
     */
    public function getThirdPartyID(string $id, string $site, int $status = 0): int
    {
        $socid = $this->db->fetchOne(
            'SELECT sa.fk_soc as fk_soc FROM llx_societe_account as sa'
            . ' WHERE sa.key_account = ?'
            . " AND sa.entity IN (" . $this->config->getEntity('societe') . ')'
            . ' AND sa.site = ? AND sa.status = ?'
            . ' AND sa.fk_soc > 0'
            . ' ORDER BY sa.site_account DESC, sa.rowid DESC',
            [$id, $site, $status],
        );

        return $socid === false || $socid === null ? 0 : (int) $socid;
    }
}
