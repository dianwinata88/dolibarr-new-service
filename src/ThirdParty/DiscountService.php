<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of DiscountAbsolute (htdocs/core/class/discount.class.php) plus
 * Societe::set_remise_except(), restricted to the CRM table
 * llx_societe_remise_except. Invoice-link fields (fk_facture,
 * fk_facture_line, fk_invoice_supplier*, fk_facture_source,
 * fk_invoice_supplier_source) are soft references to objects owned by
 * other domains: they are stored and returned verbatim but never joined
 * against (llx_facture / llx_facture_fourn do not exist in this service).
 */
final class DiscountService
{
    public ?string $error = null;
    /** @var string[] */
    public array $errors = [];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
    ) {
    }

    /**
     * Port of DiscountAbsolute::fetch(). The upstream joins on
     * llx_facture/llx_facture_fourn are skipped: ref fields stay null.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(int $rowid, ?int $fkCompany = null): ?array
    {
        $sql = 'SELECT sr.rowid, sr.fk_soc, sr.fk_user, sr.discount_type, sr.entity,'
            . ' sr.amount_ht, sr.amount_tva, sr.amount_ttc,'
            . ' sr.tva_tx, sr.vat_src_code,'
            . ' sr.amount_localtax1, sr.amount_localtax2, sr.localtax1_tx, sr.localtax1_type, sr.localtax2_tx, sr.localtax2_type,'
            . ' sr.fk_facture, sr.fk_facture_line, sr.fk_facture_source,'
            . ' sr.fk_invoice_supplier, sr.fk_invoice_supplier_line, sr.fk_invoice_supplier_source,'
            . ' sr.datec, sr.description,'
            . ' sr.multicurrency_code, sr.multicurrency_tx, sr.multicurrency_amount_ht, sr.multicurrency_amount_tva, sr.multicurrency_amount_ttc,'
            . ' NULL as ref_client, NULL as type, NULL as ref_supplier, NULL as type_supplier, NULL as datef'
            . ' FROM llx_societe_remise_except sr'
            . ' WHERE sr.entity IN (' . $this->config->getEntity('invoice') . ')'
            . ' AND sr.rowid = ' . (int) $rowid;
        if ($fkCompany !== null && $fkCompany > 0) {
            $sql .= ' AND sr.fk_soc = ' . (int) $fkCompany;
        }

        $row = $this->db->fetchAssociative($sql);
        if ($row === false) {
            return null;
        }

        // upstream maps total_* on amount_*
        $row['total_ht'] = $row['amount_ht'];
        $row['total_tva'] = $row['amount_tva'];
        $row['total_ttc'] = $row['amount_ttc'];
        $row['multicurrency_total_ht'] = $row['multicurrency_amount_ht'];
        $row['multicurrency_total_tva'] = $row['multicurrency_amount_tva'];
        $row['multicurrency_total_ttc'] = $row['multicurrency_amount_ttc'];

        return $row;
    }

    /**
     * Port of Societe::set_remise_except() → DiscountAbsolute::create().
     *
     * Returns <0 KO, >0 new rowid.
     *
     * @return int
     */
    public function setRemiseExcept(Company $company, float $remise, string $desc, string $vatrate = '', int $discountType = 0, string $priceBaseType = 'HT'): int
    {
        $remise = (float) $this->utils->price2num($remise);
        $desc = trim($desc);

        if (!($remise > 0)) {
            return -1;
        }
        if (empty($desc)) {
            return -2;
        }

        [$mysocCountryId, $mysocCountryCode] = $this->config->mysocCountry();

        $buyerCountry = $discountType === 0 ? $company->country_code : $mysocCountryCode;
        $sellerCountry = $discountType === 0 ? $mysocCountryCode : $company->country_code;

        $discount = new Discount();

        // Extract vat code and rate from the "xx (CODE)" syntax
        $vatSrcCode = '';
        if (preg_match('/\((.*)\)/', $vatrate, $reg)) {
            $vatSrcCode = $reg[1];
            $vatrate = (string) preg_replace('/\s*\(.*\)/', '', $vatrate);
        }

        $taxes = $this->utils->getTaxesFromId($vatrate, $sellerCountry, $buyerCountry);
        $discount->tva_tx = $vatrate;
        $discount->localtax1_tx = $taxes ? (float) $taxes['localtax1'] : 0.0;
        $discount->localtax1_type = $taxes ? (string) $taxes['localtax1_type'] : '0';
        $discount->localtax2_tx = $taxes ? (float) $taxes['localtax2'] : 0.0;
        $discount->localtax2_type = $taxes ? (string) $taxes['localtax2_type'] : '0';

        $discount->fk_soc = $company->id;
        $discount->socid = $company->id;
        $discount->discount_type = $discountType;
        $discount->datec = time();
        $discount->description = $desc;
        $discount->vat_src_code = $vatSrcCode;
        $discount->multicurrency_code = $company->multicurrency_code ?: null;

        // multicurrency rate from llx_multicurrency(_rate)
        $discount->multicurrency_tx = 1.0;
        if (!empty($company->multicurrency_code)) {
            $idAndTx = $this->getMulticurrencyIdAndTx((string) $company->multicurrency_code);
            $discount->fk_multicurrency = $idAndTx[0];
            if (!empty($idAndTx[1])) {
                $discount->multicurrency_tx = (float) $idAndTx[1];
            }
        }

        $discount->generateFromAmount($remise, $priceBaseType === 'TTC' ? 1 : 0, (float) $vatrate);

        return $this->create($discount);
    }

    /**
     * Port of DiscountAbsolute::create().
     *
     * Returns >0 rowid, <0 KO.
     *
     * @return int
     */
    public function create(Discount $d): int
    {
        $userid = $this->config->apiUserId();

        if (empty($d->description)) {
            $this->error = 'BadValueForPropertyDescriptionOfDiscount';

            return -1;
        }

        try {
            $this->db->insert('llx_societe_remise_except', [
                'entity' => $this->config->entity(),
                'datec' => date('Y-m-d H:i:s', (int) $d->datec ?: time()),
                'fk_soc' => (int) $d->fk_soc,
                'discount_type' => (int) $d->discount_type,
                'fk_user' => $userid,
                'description' => (string) $d->description,
                'amount_ht' => (float) $this->utils->price2num($d->amount_ht ?? 0),
                'amount_tva' => (float) $this->utils->price2num($d->amount_tva ?? 0),
                'amount_localtax1' => (float) $this->utils->price2num($d->amount_localtax1 ?? 0),
                'amount_localtax2' => (float) $this->utils->price2num($d->amount_localtax2 ?? 0),
                'amount_ttc' => (float) $this->utils->price2num($d->amount_ttc ?? 0),
                'tva_tx' => (float) $this->utils->price2num($d->tva_tx ?? 0),
                'localtax1_tx' => (float) $this->utils->price2num($d->localtax1_tx ?? 0),
                'localtax1_type' => (string) ($d->localtax1_type ?? '0'),
                'localtax2_tx' => (float) $this->utils->price2num($d->localtax2_tx ?? 0),
                'localtax2_type' => (string) ($d->localtax2_type ?? '0'),
                'vat_src_code' => (string) ($d->vat_src_code ?? ''),
                'multicurrency_amount_ht' => (float) $this->utils->price2num($d->multicurrency_amount_ht ?? 0),
                'multicurrency_amount_tva' => (float) $this->utils->price2num($d->multicurrency_amount_tva ?? 0),
                'multicurrency_amount_ttc' => (float) $this->utils->price2num($d->multicurrency_amount_ttc ?? 0),
                'fk_facture_source' => $d->fk_facture_source ?: null,
                'fk_invoice_supplier_source' => $d->fk_invoice_supplier_source ?: null,
                'fk_facture' => $d->fk_facture ?: null,
                'fk_facture_line' => $d->fk_facture_line ?: null,
                'fk_invoice_supplier' => $d->fk_invoice_supplier ?: null,
                'fk_invoice_supplier_line' => $d->fk_invoice_supplier_line ?: null,
                'multicurrency_code' => $d->multicurrency_code ?: null,
                'multicurrency_tx' => (float) $this->utils->price2num($d->multicurrency_tx ?? 0),
            ]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Port of DiscountAbsolute::delete() — refused when linked to an
     * invoice/line, or when another discount of the same split is used.
     *
     * Returns >0 OK, <0 KO.
     *
     * @return int
     */
    public function delete(int $rowid): int
    {
        $row = $this->db->fetchAssociative('SELECT * FROM llx_societe_remise_except WHERE rowid = ?', [$rowid]);
        if ($row === false) {
            return -2;
        }

        // Check that the discount is not already used via fk_facture/fk_invoice_supplier
        if (
            !empty($row['fk_facture']) || !empty($row['fk_facture_line'])
            || !empty($row['fk_invoice_supplier']) || !empty($row['fk_invoice_supplier_line'])
        ) {
            $this->error = 'ErrorThisPartOrAnotherIsAlreadyUsedSoDiscountSerieCantBeRemoved';

            return -2;
        }

        // Check discount serie: if another split of the same source is used, refuse
        if (!empty($row['fk_facture_source'])) {
            $nb = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM llx_societe_remise_except'
                . ' WHERE fk_facture_source = ' . (int) $row['fk_facture_source']
                . ' AND (fk_facture IS NOT NULL OR fk_facture_line IS NOT NULL)',
            );
            if ($nb > 0) {
                $this->error = 'ErrorThisPartOrAnotherIsAlreadyUsedSoDiscountSerieCantBeRemoved';

                return -2;
            }
        }
        if (!empty($row['fk_invoice_supplier_source'])) {
            $nb = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM llx_societe_remise_except'
                . ' WHERE fk_invoice_supplier_source = ' . (int) $row['fk_invoice_supplier_source']
                . ' AND (fk_invoice_supplier IS NOT NULL OR fk_invoice_supplier_line IS NOT NULL)',
            );
            if ($nb > 0) {
                $this->error = 'ErrorThisPartOrAnotherIsAlreadyUsedSoDiscountSerieCantBeRemoved';

                return -2;
            }
        }

        $this->db->executeStatement(
            'DELETE FROM llx_societe_remise_except WHERE rowid = ' . (int) $rowid
            . ' AND fk_facture IS NULL AND fk_facture_line IS NULL'
            . ' AND fk_invoice_supplier IS NULL AND fk_invoice_supplier_line IS NULL',
        );

        return 1;
    }

    /**
     * Port of DiscountAbsolute::getAvailableDiscounts().
     */
    public function getAvailableDiscounts(Company $company, string $filter = '', float $maxvalue = 0, int $discountType = 0, bool $multicurrency = false): float
    {
        $field = $multicurrency ? 'multicurrency_amount_ttc' : 'amount_ttc';
        $sql = 'SELECT SUM(' . $field . ') as total'
            . ' FROM llx_societe_remise_except'
            . ' WHERE entity = ' . $this->config->entity()
            . ' AND discount_type = ' . (int) $discountType;
        if ($discountType === 0) {
            $sql .= ' AND (fk_facture IS NULL AND fk_facture_line IS NULL)';
        } else {
            $sql .= ' AND (fk_invoice_supplier IS NULL AND fk_invoice_supplier_line IS NULL)';
        }
        if ($company->id) {
            $sql .= ' AND fk_soc = ' . (int) $company->id;
        }
        if ($filter) {
            $sql .= ' AND (' . $filter . ')';
        }
        if ($maxvalue) {
            $sql .= ' AND ' . $field . ' <= ' . (float) $maxvalue;
        }

        return (float) ($this->db->fetchOne($sql) ?? 0);
    }

    /**
     * MultiCurrency::getIdAndTxFromCode().
     *
     * @return array{0:int,1:float|null}
     */
    public function getMulticurrencyIdAndTx(string $code): array
    {
        $sql = 'SELECT m.rowid, mc.rate, mc.rate_direct FROM llx_multicurrency m'
            . ' LEFT JOIN llx_multicurrency_rate mc ON (m.rowid = mc.fk_multicurrency)'
            . " WHERE m.code = " . $this->db->quote($code)
            . ' AND m.entity IN (' . $this->config->getEntity('multicurrency') . ')'
            . ' ORDER BY mc.date_sync DESC LIMIT 1';

        try {
            $row = $this->db->fetchAssociative($sql);
        } catch (\Throwable) {
            return [0, 1];
        }

        return $row === false ? [0, 1] : [(int) $row['rowid'], $row['rate'] === null ? null : (float) $row['rate']];
    }
}
