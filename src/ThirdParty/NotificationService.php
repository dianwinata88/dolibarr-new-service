<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of Notify (htdocs/core/class/notify.class.php) for the API slice:
 * CRUD over llx_notify_def + the llx_c_action_trigger lookup used by
 * notificationsbycode.
 */
final class NotificationService
{
    public ?string $error = null;
    /** @var string[] */
    public array $errors = [];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
    ) {
    }

    /**
     * Port of Notify::fetch() — SELECT rowid, fk_action as event, fk_soc as
     * socid, fk_contact as contact_id, type, datec, tms as datem.
     * When $socId is given, adds fk_soc = $socId.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $id, ?int $socId = null): ?array
    {
        $sql = 'SELECT rowid, fk_action as event, fk_soc as socid, fk_contact as contact_id, type, datec, tms as datem'
            .' FROM llx_notify_def'
            .' WHERE entity IN ('.$this->config->getEntity('notify_def').')'
            .' AND rowid = '.(int) $id;
        if ($socId !== null) {
            $sql .= ' AND fk_soc = '.(int) $socId;
        }

        $row = $this->db->fetchAssociative($sql);

        return $row === false ? null : $row;
    }

    /**
     * Port of Notify::create(): INSERT (entity, fk_soc, fk_action,
     * fk_contact, type, datec).
     *
     * @return int >0 rowid, <0 KO
     */
    public function create(int $socId, int $event, int $contactId, string $type = 'email'): int
    {
        try {
            $this->db->insert('llx_notify_def', [
                'entity' => $this->config->entity(),
                'fk_soc' => $socId,
                'fk_action' => $event,
                'fk_contact' => $contactId,
                'type' => $type,
                'datec' => date('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Port of Notify::update(): writes type, fk_soc, fk_action, fk_contact.
     */
    public function update(int $id, int $socId, int $event, int $contactId, string $type = 'email'): int
    {
        try {
            $this->db->update('llx_notify_def', [
                'type' => $type,
                'fk_soc' => $socId,
                'fk_action' => $event,
                'fk_contact' => $contactId,
            ], ['rowid' => $id]);

            return 1;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }
    }

    /**
     * Port of Notify::delete().
     *
     * @return int >0 OK, <0 KO
     */
    public function delete(int $id): int
    {
        try {
            $this->db->executeStatement(
                'DELETE FROM llx_notify_def WHERE rowid = '.(int) $id
                .' AND entity IN ('.$this->config->getEntity('notify_def').')',
            );

            return 1;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }
    }

    /**
     * Resolve an action trigger code to its llx_c_action_trigger rowid.
     */
    public function actionTriggerIdForCode(string $code): ?int
    {
        $id = $this->db->fetchOne('SELECT rowid FROM llx_c_action_trigger WHERE code = ?', [$code]);

        return $id === false ? null : (int) $id;
    }

    /**
     * Notifications list for a thirdparty (getCompanyNotification).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForCompany(int $socid): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT rowid as id, fk_action as event, fk_soc as socid, fk_contact as contact_id, type, datec, tms'
            .' FROM llx_notify_def WHERE fk_soc = '.(int) $socid,
        );
    }

    /**
     * Duplicate detection used by createCompanyNotification: any row with
     * the same (fk_action, fk_soc, fk_contact) triple.
     */
    public function exists(int $socId, int $event, int $contactId): bool
    {
        return $this->db->fetchOne(
            'SELECT rowid FROM llx_notify_def WHERE fk_soc = '.(int) $socId
            .' AND fk_action = '.(int) $event.' AND fk_contact = '.(int) $contactId
            .' AND entity IN ('.$this->config->getEntity('notify_def').')',
        ) !== false;
    }
}
