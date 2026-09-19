<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of SocieteAccount (htdocs/societe/class/societeaccount.class.php).
 * The upstream class uses a $fields array + createCommon/updateCommon;
 * here the column list is driven by the same field list.
 */
final class SocieteAccountService
{
    public ?string $error = null;
    /** @var string[] */
    public array $errors = [];

    /** Columns writable through the common create/update path (upstream $fields). */
    private const FIELDS = [
        'login', 'pass_encoding', 'pass_crypted', 'pass_temp', 'fk_soc', 'site', 'fk_website',
        'site_account', 'key_account', 'date_last_login', 'date_previous_login',
        'date_last_reset_password', 'note_private', 'date_creation', 'fk_user_creat',
        'fk_user_modif', 'import_key', 'status',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(int $id): ?array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM llx_societe_account WHERE rowid = ?', [$id]);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchBySocAndSite(int $socid, string $site): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM llx_societe_account WHERE fk_soc = ? AND site = ?',
            [$socid, $site],
        );

        return $row === false ? null : $row;
    }

    /**
     * Port of SocieteAccount::create() — insert with the special rules:
     * site 'dolibarr_website' requires fk_website>0 (-1),
     * site 'dolibarr_portal' refuses duplicate logins (-2, ErrorLoginAlreadyExists).
     *
     * @param array<string, mixed> $values
     * @return int >0 rowid, <0 KO
     */
    public function create(array $values): int
    {
        if (($values['site'] ?? '') === 'dolibarr_website' && empty($values['fk_website'])) {
            $this->error = 'SocieteAccount create error: site dolibarr_website requires fk_website';

            return -1;
        }
        if (($values['site'] ?? '') === 'dolibarr_portal') {
            $nb = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM llx_societe_account WHERE login = '.$this->db->quote((string) ($values['login'] ?? '')),
            );
            if ($nb > 0) {
                $this->error = 'ErrorLoginAlreadyExists';

                return -2;
            }
        }

        $insert = ['entity' => $this->config->entity()];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $insert[$field] = $values[$field];
            }
        }
        $insert['date_creation'] = $insert['date_creation'] ?? date('Y-m-d H:i:s');
        $insert['fk_user_creat'] = $insert['fk_user_creat'] ?? $this->config->apiUserId();

        try {
            $this->db->insert('llx_societe_account', $insert);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Port of SocieteAccount::update() (updateCommon on the $fields set).
     *
     * @param array<string, mixed> $values
     */
    public function update(int $id, array $values): int
    {
        $sets = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $v = $values[$field];
            if ($v === null) {
                $sets[] = $field.' = null';
            } elseif (is_int($v) || is_float($v)) {
                $sets[] = $field.' = '.$v;
            } else {
                $sets[] = $field.' = '.$this->db->quote((string) $v);
            }
        }
        $sets[] = 'fk_user_modif = '.($this->config->apiUserId() ?: 'null');

        try {
            $this->db->executeStatement('UPDATE llx_societe_account SET '.implode(', ', $sets).' WHERE rowid = '.(int) $id);

            return 1;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }
    }

    public function delete(int $id): int
    {
        $this->db->executeStatement('DELETE FROM llx_societe_account WHERE rowid = '.(int) $id);

        return 1;
    }
}
