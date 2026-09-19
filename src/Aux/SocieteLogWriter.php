<?php

declare(strict_types=1);

namespace App\Aux;

use Doctrine\DBAL\Connection;

/**
 * Writer for llx_societe_log — the legacy company history table. Upstream
 * only ever wrote it from Societe::set_status() (removed after v3.1) with
 * the row "(now, fk_soc, fk_statut, fk_user, author, label)" and label
 * "Change statut from X to Y". The same row shape is used for the
 * company-modify write-through of this service.
 */
final class SocieteLogWriter
{
    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrContext $config,
    ) {
    }

    /**
     * Insert one llx_societe_log row (datel = now).
     */
    public function log(int $socid, ?int $statut, ?int $userId, string $author, string $label): void
    {
        $this->db->insert('llx_societe_log', [
            'datel' => date('Y-m-d H:i:s'),
            'fk_soc' => $socid,
            'fk_statut' => $statut,
            'fk_user' => $userId,
            'author' => mb_substr($author, 0, 30),
            'label' => mb_substr($label, 0, 128),
        ]);
    }

    /** "company modify -> log row" write-through. */
    public function logModify(int $socid, ?int $statut): void
    {
        $this->log($socid, $statut, $this->config->apiUserId(), $this->config->apiUserLogin(), 'COMPANY_MODIFY');
    }
}
