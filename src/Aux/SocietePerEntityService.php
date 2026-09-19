<?php

declare(strict_types=1);

namespace App\Aux;

use App\Entity\Societe;
use Doctrine\DBAL\Connection;

/**
 * Port of the llx_societe_perentity write-through of Societe
 * (htdocs/societe/class/societe.class.php), active only when
 * MAIN_COMPANY_PERENTITY_SHARED is set:
 *  - Societe::update()  -> syncFromSociete() (delete + insert for current entity)
 *  - Societe::setBankAccount() / setPaymentTerms() / setPaymentMethods()
 *    -> per-column upsert into the current entity's row
 *  - Societe::delete()  -> deleteForThirdparty()
 */
final class SocietePerEntityService
{
    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrContext $config,
    ) {
    }

    /** Upstream gate: getDolGlobalString('MAIN_COMPANY_PERENTITY_SHARED'). */
    public function isShared(): bool
    {
        return $this->config->getBool('MAIN_COMPANY_PERENTITY_SHARED');
    }

    /**
     * Port of the "update accountancy for this entity" block of
     * Societe::update(): delete then re-insert the current entity row.
     *
     * @return int negative on failure, 1 on success (0 when sharing is off — nothing to do)
     */
    public function syncFromSociete(Societe $societe): int
    {
        if (!$this->isShared()) {
            return 0;
        }

        try {
            $this->db->executeStatement(
                'DELETE FROM llx_societe_perentity WHERE fk_soc = ? AND entity = ?',
                [$societe->getRowid(), $this->config->entity()],
            );
            $this->db->insert('llx_societe_perentity', [
                'fk_soc' => $societe->getRowid(),
                'entity' => $this->config->entity(),
                'vat_reverse_charge' => empty($societe->getVatReverseCharge()) ? 0 : 1,
                'accountancy_code_customer_general' => (string) $societe->getAccountancyCodeCustomerGeneral(),
                'accountancy_code_customer' => (string) $societe->getCodeCompta(),
                'accountancy_code_supplier_general' => (string) $societe->getAccountancyCodeSupplierGeneral(),
                'accountancy_code_supplier' => (string) $societe->getCodeComptaFournisseur(),
                'accountancy_code_buy' => (string) $societe->getAccountancyCodeBuy(),
                'accountancy_code_sell' => (string) $societe->getAccountancyCodeSell(),
                'fk_account' => ($societe->getFkAccount() ?? 0) > 0 ? $societe->getFkAccount() : null,
                'mode_reglement' => !empty($societe->getModeReglement()) ? $societe->getModeReglement() : null,
                'cond_reglement' => !empty($societe->getCondReglement()) ? $societe->getCondReglement() : null,
                'mode_reglement_supplier' => !empty($societe->getModeReglementSupplier())
                    ? $societe->getModeReglementSupplier() : null,
                'cond_reglement_supplier' => !empty($societe->getCondReglementSupplier())
                    ? $societe->getCondReglementSupplier() : null,
            ]);
        } catch (\Throwable) {
            return -1; // upstream error: 'ErrorFailedToUpdateAccountancyForEntity'
        }

        return 1;
    }

    /**
     * Port of Societe::setBankAccount() when MAIN_COMPANY_PERENTITY_SHARED is
     * on (writes llx_societe_perentity.fk_account instead of llx_societe).
     * Upstream quirk kept verbatim: a negative $fkAccount is stored as 0
     * ('NULL' string cast to int).
     *
     * @return int negative on failure, 1 on success
     */
    public function setBankAccount(int $socid, int $fkAccount): int
    {
        if (!$this->isShared()) {
            return 0;
        }
        $value = $fkAccount < 0 ? 0 : $fkAccount; // upstream casts 'NULL' to (int) = 0

        return $this->upsertColumn($socid, 'fk_account', $value) ? 1 : -1;
    }

    /**
     * Port of Societe::setPaymentTerms() when shared: deposit_percent stays
     * global on llx_societe; cond_reglement(_supplier) is upserted per entity.
     *
     * @param int|string $id upstream accepts '0' and negative ids verbatim
     * @return int negative on failure, positive on success
     */
    public function setPaymentTerms(
        int $socid,
        int|string $id,
        ?float $depositPercent = null,
        bool $supplier = false,
    ): int {
        if (!$this->isShared()) {
            return 0;
        }

        $fieldname = $supplier ? 'cond_reglement_supplier' : 'cond_reglement';

        if (empty($depositPercent) || $depositPercent < 0) {
            $depositPercent = (float) $this->db->fetchOne(
                'SELECT deposit_percent FROM llx_c_payment_term WHERE rowid = ?',
                [(int) $id],
            );
        }
        if ($depositPercent > 100) {
            $depositPercent = 100;
        }

        try {
            $this->db->executeStatement(
                'UPDATE llx_societe SET deposit_percent = ? WHERE rowid = ?',
                [empty($depositPercent) ? null : (string) $depositPercent, $socid],
            );
        } catch (\Throwable) {
            return -1;
        }

        $value = ($id > 0 || $id === '0' || $id === 0) ? (int) $id : null;

        return $this->upsertColumn($socid, $fieldname, $value) ? 1 : -1;
    }

    /**
     * Port of Societe::setPaymentMethods() when shared: mode_reglement
     * (_supplier) upserted per entity; llx_societe untouched.
     *
     * @return int negative on failure, positive on success
     */
    public function setPaymentMethods(int $socid, int|string $id, bool $supplier = false): int
    {
        if (!$this->isShared()) {
            return 0;
        }

        $fieldname = $supplier ? 'mode_reglement_supplier' : 'mode_reglement';
        $value = ($id > 0 || $id === '0' || $id === 0) ? (int) $id : null;

        return $this->upsertColumn($socid, $fieldname, $value) ? 1 : -1;
    }

    /**
     * Port of the Societe::delete() block: drop every perentity row of the
     * company when sharing is on.
     */
    public function deleteForThirdparty(int $socid): void
    {
        if (!$this->isShared()) {
            return;
        }
        $this->db->executeStatement('DELETE FROM llx_societe_perentity WHERE fk_soc = ?', [$socid]);
    }

    /**
     * UPDATE the current-entity row; INSERT it when no row was affected —
     * the upstream "upsert" pattern.
     */
    private function upsertColumn(int $socid, string $field, ?int $value): bool
    {
        try {
            $affected = $this->db->executeStatement(
                'UPDATE llx_societe_perentity SET ' . $field . ' = ? WHERE fk_soc = ? AND entity = ?',
                [$value, $socid, $this->config->entity()],
            );
            if ($affected === 0) {
                $this->db->insert('llx_societe_perentity', [
                    'fk_soc' => $socid,
                    'entity' => $this->config->entity(),
                    $field => $value,
                ]);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
