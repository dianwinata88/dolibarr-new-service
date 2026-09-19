<?php

declare(strict_types=1);

namespace App\Pricing\Service;

use App\Entity\Societe;
use App\Entity\SocieteRemiseExcept;
use App\Pricing\DolibarrContext;
use App\Pricing\Util\Price2Num;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Port of Dolibarr's DiscountAbsolute (htdocs/core/class/discount.class.php)
 * plus the discount-writing part of Societe::set_remise_except()
 * (htdocs/societe/class/societe.class.php).
 *
 * Rows live in llx_societe_remise_except. A row is "available" until it is
 * linked to an invoice (fk_facture) or an invoice line (fk_facture_line) —
 * or the supplier equivalents (fk_invoice_supplier / ..._line).
 *
 * Out of scope vs upstream: llx_facture / llx_facture_fourn do not exist in
 * this service, so anything that reads or writes invoice rows
 * (ref/type of the source invoice, resetting paye/fk_statut on delete) is
 * skipped. Multicurrency rates resolve to (0, 1) exactly like
 * MultiCurrency::getIdAndTxFromCode() when the code is unknown —
 * llx_multicurrency is not ported either.
 */
final class DiscountManager
{
    /**
     * Last error string, mirroring upstream ->error usage. Null on success.
     */
    public ?string $error = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DolibarrContext $context,
    ) {
    }

    /**
     * Societe::set_remise_except().
     *
     * @return int a negative value on failure, the id of the created discount row on success
     */
    public function setRemiseExcept(
        Societe $company,
        float|string $remise,
        ?int $userId,
        string $desc,
        string $vatrate = '',
        int $discountType = 0,
        string $priceBaseType = 'HT',
    ): int {
        $this->error = null;

        $remise = Price2Num::num($remise);
        $desc = trim($desc);

        if (!($remise > 0)) {
            $this->error = 'ErrorWrongValueForParameter 1';

            return -1;
        }
        if (!$desc) {
            $this->error = 'ErrorWrongValueForParameter 3';

            return -2;
        }

        if (!($company->getRowid() > 0)) {
            return 0;
        }

        // Upstream resolves local taxes through getTaxesFromId(); with no VAT
        // dictionary / third-party country data ported, both localtaxes are 0.
        $localtax1 = 0.0;
        $localtax2 = 0.0;
        $localtax1Type = 0;
        $localtax2Type = 0;

        // Separate VAT code from VAT rate string ('20 (ABC)' -> '20' + 'ABC')
        $vatSrcCode = '';
        $reg = [];
        if (preg_match('/\((.*)\)/', $vatrate, $reg)) {
            $vatSrcCode = $reg[1];
            $vatrate = (string) preg_replace('/\s*\(.*\)/', '', $vatrate);
        }

        $discount = new SocieteRemiseExcept();
        $discount->setFkSoc((int) $company->getRowid());
        $discount->setDiscountType($discountType);
        $discount->setMulticurrencyCode($company->getMulticurrencyCode());

        // MultiCurrency::getIdAndTxFromCode() -> (0, 1) when the currency is
        // unknown/absent: fk_multicurrency = 0, rate = 1.
        $multicurrencyTx = 1.0;
        $discount->setMulticurrencyTx($multicurrencyTx);

        $vatTx = Price2Num::num($vatrate);

        $this->generateFromAmount(
            $discount,
            $remise,
            $priceBaseType === 'TTC' ? 1 : 0,
            $vatTx,
            $localtax1,
            $localtax2,
            $localtax1Type,
            $localtax2Type,
        );

        $discount->setVatSrcCode($vatSrcCode);
        $discount->setDescription($desc);

        $result = $this->create($discount, $userId);
        if ($result > 0) {
            return $result;
        }

        $this->error = $this->error ?? 'Error creating discount';

        return -3;
    }

    /**
     * DiscountAbsolute::create().
     *
     * @return int a negative value on failure, the rowid of the new discount on success
     */
    public function create(SocieteRemiseExcept $discount, ?int $userId): int
    {
        // Clean parameters
        $discount->setAmountHt(Price2Num::num($discount->getAmountHt()));
        $discount->setAmountTva(Price2Num::num($discount->getAmountTva()));
        $discount->setAmountTtc(Price2Num::num($discount->getAmountTtc()));
        $discount->setTvaTx(Price2Num::num($discount->getTvaTx()));
        $discount->setLocaltax1Tx(Price2Num::num($discount->getLocaltax1Tx()));
        $discount->setLocaltax1Type((string) (int) Price2Num::num($discount->getLocaltax1Type()));
        $discount->setLocaltax2Tx(Price2Num::num($discount->getLocaltax2Tx()));
        $discount->setLocaltax2Type((string) (int) Price2Num::num($discount->getLocaltax2Type()));

        $discount->setMulticurrencyAmountHt(Price2Num::num($discount->getMulticurrencyAmountHt()));
        $discount->setMulticurrencyAmountTva(Price2Num::num($discount->getMulticurrencyAmountTva()));
        $discount->setMulticurrencyAmountTtc(Price2Num::num($discount->getMulticurrencyAmountTtc()));

        if (empty($discount->getMulticurrencyAmountHt())) {
            $discount->setMulticurrencyAmountHt(0.0);
        }
        if (empty($discount->getMulticurrencyAmountTva())) {
            $discount->setMulticurrencyAmountTva(0.0);
        }
        if (empty($discount->getMulticurrencyAmountTtc())) {
            $discount->setMulticurrencyAmountTtc(0.0);
        }
        if (empty($discount->getTvaTx())) {
            $discount->setTvaTx(0.0);
        }
        if (empty($discount->getLocaltax1Tx())) {
            $discount->setLocaltax1Tx(0.0);
            $discount->setLocaltax1Type('0');
        }
        if (empty($discount->getLocaltax2Tx())) {
            $discount->setLocaltax2Tx(0.0);
            $discount->setLocaltax2Type('0');
        }

        // Check parameters
        if ($discount->getDescription() === '') {
            $this->error = 'BadValueForPropertyDescriptionOfDiscount';

            return -1;
        }

        $discount->setEntity($this->context->entity());
        $discount->setFkUser((int) ($userId ?? $discount->getFkUser()));
        if ($discount->getDatec() === null) {
            $discount->setDatec(new \DateTime());
        }

        $this->em->persist($discount);
        $this->em->flush();

        return (int) $discount->getRowid();
    }

    /**
     * DiscountAbsolute::delete().
     *
     * If fk_facture_source / fk_invoice_supplier_source is set, the whole
     * family (every row sharing the source) is removed, but only when none of
     * the family members is used. The upstream "reset paye/fk_statut on the
     * source invoice" step is skipped: llx_facture is not ported.
     *
     * @return int a negative value on failure, a positive value on success
     */
    public function delete(SocieteRemiseExcept $discount): int
    {
        $conn = $this->em->getConnection();

        if ($discount->getFkFactureSource()) {
            $nb = (int) $conn->fetchOne(
                'SELECT COUNT(rowid) FROM llx_societe_remise_except'
                . ' WHERE (fk_facture_line IS NOT NULL OR fk_facture IS NOT NULL)'
                . ' AND fk_facture_source = ?',
                [$discount->getFkFactureSource()],
            );
            if ($nb > 0) {
                $this->error = 'ErrorThisPartOrAnotherIsAlreadyUsedSoDiscountSerieCantBeRemoved';

                return -2;
            }
        }

        if ($discount->getFkInvoiceSupplierSource()) {
            $nb = (int) $conn->fetchOne(
                'SELECT COUNT(rowid) FROM llx_societe_remise_except'
                . ' WHERE (fk_invoice_supplier_line IS NOT NULL OR fk_invoice_supplier IS NOT NULL)'
                . ' AND fk_invoice_supplier_source = ?',
                [$discount->getFkInvoiceSupplierSource()],
            );
            if ($nb > 0) {
                $this->error = 'ErrorThisPartOrAnotherIsAlreadyUsedSoDiscountSerieCantBeRemoved';

                return -2;
            }
        }

        $qb = $conn->createQueryBuilder()
            ->delete('llx_societe_remise_except')
            ->andWhere('fk_facture_line IS NULL')
            ->andWhere('fk_facture IS NULL')
            ->andWhere('fk_invoice_supplier_line IS NULL')
            ->andWhere('fk_invoice_supplier IS NULL');

        if ($discount->getFkFactureSource()) {
            // Delete all lines of same series
            $qb->andWhere('fk_facture_source = :src')
                ->setParameter('src', $discount->getFkFactureSource());
        } elseif ($discount->getFkInvoiceSupplierSource()) {
            // Delete all lines of same series
            $qb->andWhere('fk_invoice_supplier_source = :src')
                ->setParameter('src', $discount->getFkInvoiceSupplierSource());
        } else {
            // Delete only this line
            $qb->andWhere('rowid = :id')
                ->setParameter('id', $discount->getRowid());
        }

        $qb->executeStatement();

        // Rows were removed through DBAL: detach the (possibly dirty) entity
        // so a later flush cannot write it back or delete a used row that
        // the guarded DELETE intentionally skipped.
        $this->em->detach($discount);

        return 1;
    }

    /**
     * DiscountAbsolute::getAvailableDiscounts().
     *
     * @return array{amount: float, multicurrency_amount: float}
     */
    public function getAvailableDiscounts(
        ?int $socid = null,
        ?int $userId = null,
        string $filter = '',
        float $maxvalue = 0.0,
        int $discountType = 0,
    ): array {
        $conn = $this->em->getConnection();

        $sql = 'SELECT SUM(rc.amount_ttc) AS amount, SUM(rc.multicurrency_amount_ttc) AS multicurrency_amount'
            . ' FROM llx_societe_remise_except AS rc'
            . ' WHERE rc.entity = ' . ((int) $this->context->entity())
            . ' AND rc.discount_type = ' . ((int) $discountType);
        $params = [];

        if ($discountType !== 0) {
            // Available from supplier
            $sql .= ' AND (rc.fk_invoice_supplier IS NULL AND rc.fk_invoice_supplier_line IS NULL)';
        } else {
            // Available to customer
            $sql .= ' AND (rc.fk_facture IS NULL AND rc.fk_facture_line IS NULL)';
        }
        if ($socid !== null) {
            $sql .= ' AND rc.fk_soc = :socid';
            $params['socid'] = $socid;
        }
        if ($userId !== null) {
            $sql .= ' AND rc.fk_user = :userid';
            $params['userid'] = $userId;
        }
        if ($filter !== '') {
            $sql .= ' AND (' . $filter . ')';
        }
        if ($maxvalue) {
            $sql .= ' AND rc.amount_ttc <= ' . Price2Num::num($maxvalue);
        }

        $row = $conn->fetchAssociative($sql, $params);

        return [
            'amount' => (float) ($row['amount'] ?? 0),
            'multicurrency_amount' => (float) ($row['multicurrency_amount'] ?? 0),
        ];
    }

    /**
     * DiscountAbsolute::fetch() by rowid, scoped to the current entity
     * (upstream: sr.entity IN (getEntity('invoice'))).
     */
    public function fetch(int $rowid): ?SocieteRemiseExcept
    {
        $discount = $this->em->find(SocieteRemiseExcept::class, $rowid);
        if ($discount === null || $discount->getEntity() !== $this->context->entity()) {
            return null;
        }

        return $discount;
    }

    /**
     * api_thirdparties::splitdiscount() — split one discount into two.
     *
     * @return array{0: int, 1: int} the two new rowids
     *
     * @throws \RuntimeException when any step of the delete/create chain fails
     */
    public function split(SocieteRemiseExcept $discount, float $amountTtc1, float $amountTtc2, ?int $userId): array
    {
        $mkclone = function (SocieteRemiseExcept $source): SocieteRemiseExcept {
            $new = new SocieteRemiseExcept();
            $new->setFkSoc($source->getFkSoc());
            $new->setDiscountType($source->getDiscountType());
            $new->setDatec($source->getDatec());
            $new->setTvaTx($source->getTvaTx());
            $new->setLocaltax1Tx($source->getLocaltax1Tx());
            $new->setLocaltax1Type($source->getLocaltax1Type());
            $new->setLocaltax2Tx($source->getLocaltax2Tx());
            $new->setLocaltax2Type($source->getLocaltax2Type());
            $new->setVatSrcCode($source->getVatSrcCode());
            $new->setFkUser($source->getFkUser());
            $new->setFkFacture($source->getFkFacture());
            $new->setFkFactureLine($source->getFkFactureLine());
            $new->setFkFactureSource($source->getFkFactureSource());
            $new->setFkInvoiceSupplier($source->getFkInvoiceSupplier());
            $new->setFkInvoiceSupplierLine($source->getFkInvoiceSupplierLine());
            $new->setFkInvoiceSupplierSource($source->getFkInvoiceSupplierSource());
            $new->setMulticurrencyCode($source->getMulticurrencyCode());
            $new->setMulticurrencyTx($source->getMulticurrencyTx());
            if ($source->getDescription() === '(CREDIT_NOTE)' || $source->getDescription() === '(DEPOSIT)') {
                $new->setDescription($source->getDescription());
            }

            return $new;
        };

        $new1 = $mkclone($discount);
        $new2 = $mkclone($discount);
        if ($discount->getDescription() !== '(CREDIT_NOTE)' && $discount->getDescription() !== '(DEPOSIT)') {
            $new1->setDescription($discount->getDescription() . ' (1)');
            $new2->setDescription($discount->getDescription() . ' (2)');
        }

        $new1->setAmountTtc($amountTtc1);
        $new2->setAmountTtc(Price2Num::num($discount->getAmountTtc() - $new1->getAmountTtc()));
        $new1->setAmountHt(Price2Num::num($new1->getAmountTtc() / (1 + $new1->getTvaTx() / 100), 'MT'));
        $new2->setAmountHt(Price2Num::num($new2->getAmountTtc() / (1 + $new2->getTvaTx() / 100), 'MT'));
        $new1->setAmountTva(Price2Num::num($new1->getAmountTtc() - $new1->getAmountHt()));
        $new2->setAmountTva(Price2Num::num($new2->getAmountTtc() - $new2->getAmountHt()));
        // localtax amounts: upstream keeps total_localtax1/2 unset on the clones
        // (split only handles plain VAT), so they insert as 0.
        $new1->setAmountLocaltax1(0.0);
        $new2->setAmountLocaltax1(0.0);
        $new1->setAmountLocaltax2(0.0);
        $new2->setAmountLocaltax2(0.0);

        $mcRatio = $discount->getMulticurrencyAmountTtc() / $discount->getAmountTtc();
        $new1->setMulticurrencyAmountTtc($amountTtc1 * $mcRatio);
        $new2->setMulticurrencyAmountTtc(
            Price2Num::num($discount->getMulticurrencyAmountTtc() - $new1->getMulticurrencyAmountTtc()),
        );
        $new1->setMulticurrencyAmountHt(
            Price2Num::num($new1->getMulticurrencyAmountTtc() / (1 + $new1->getTvaTx() / 100), 'MT'),
        );
        $new2->setMulticurrencyAmountHt(
            Price2Num::num($new2->getMulticurrencyAmountTtc() / (1 + $new2->getTvaTx() / 100), 'MT'),
        );
        $new1->setMulticurrencyAmountTva(
            Price2Num::num($new1->getMulticurrencyAmountTtc() - $new1->getMulticurrencyAmountHt()),
        );
        $new2->setMulticurrencyAmountTva(
            Price2Num::num($new2->getMulticurrencyAmountTtc() - $new2->getMulticurrencyAmountHt()),
        );

        $this->em->wrapInTransaction(function () use ($discount, $new1, $new2): void {
            // Force the source ids to 0 before delete() so only the single
            // record is removed, not the whole family sharing the source.
            $discount->setFkFactureSource(0);
            $discount->setFkInvoiceSupplierSource(0);
            if ($this->delete($discount) <= 0) {
                throw new \RuntimeException('Operation fail');
            }
            if (
                $this->create($new1, $new1->getFkUser() ?: null) <= 0
                || $this->create($new2, $new2->getFkUser() ?: null) <= 0
            ) {
                throw new \RuntimeException('Operation fail');
            }
        });

        return [(int) $new1->getRowid(), (int) $new2->getRowid()];
    }

    /**
     * DiscountAbsolute::generateFromAmount().
     *
     * Fills amount_ht / amount_tva / amount_localtax* / amount_ttc (+ the
     * multicurrency_* copies) on the entity from a base amount.
     */
    private function generateFromAmount(
        SocieteRemiseExcept $discount,
        float $amount,
        int $amountType,
        float $tvaTx,
        float $localtax1Tx,
        float $localtax2Tx,
        int $localtax1Type = 1,
        int $localtax2Type = 1,
    ): void {
        $tvaTxPct = $tvaTx / 100;
        $localtax1TxPct = $localtax1Tx / 100;
        $localtax2TxPct = $localtax2Tx / 100;

        $multicurrencyTx = (float) ($discount->getMulticurrencyTx() ?? 1.0);

        // Localtax types 2, 4, 6 are calculated on (ht + vat)
        $localtax1Type2 = ($localtax1Type > 0 && $localtax1Type % 2 === 0) ? 1 : 0;
        $localtax2Type2 = ($localtax2Type > 0 && $localtax2Type % 2 === 0) ? 1 : 0;

        $totalHt = null;
        $totalTva = null;
        $totalTtc = null;
        $totalLocaltax1 = null;
        $totalLocaltax2 = null;
        $mcTotalHt = null;
        $mcTotalTva = null;
        $mcTotalTtc = null;

        if ($amountType === 1) {
            // TTC
            $totalTtc = Price2Num::num($amount, 'MT');
            $ttc = $totalTtc;

            $lt1 = 0.0;
            $lt2 = 0.0;
            $txBeforeVat = 0.0;
            $txBeforeVatWithout1 = 0.0;
            $txBeforeVatWithout2 = 0.0;
            $txAfterVat = 0.0;
            $txAfterVatWithout1 = 0.0;
            $txAfterVatWithout2 = 0.0;
            if ($localtax1Type2 && $localtax1TxPct > 0) {
                $txAfterVat += $localtax1TxPct;
                $txAfterVatWithout2 += $localtax1TxPct;
            } else {
                $txBeforeVat += $localtax1TxPct;
                $txBeforeVatWithout2 += $localtax1TxPct;
            }
            if ($localtax2Type2 && $localtax2TxPct > 0) {
                $txAfterVat += $localtax2TxPct;
                $txAfterVatWithout1 += $localtax2TxPct;
            } else {
                $txBeforeVat += $localtax2TxPct;
                $txBeforeVatWithout1 += $localtax2TxPct;
            }
            $txBeforeVat += $tvaTxPct;
            $txBeforeVatWithout1 += $tvaTxPct;
            $txBeforeVatWithout2 += $tvaTxPct;

            if ($localtax1Type2 && $localtax1TxPct > 0) {
                $lt1 = Price2Num::num($ttc - $ttc / (1 + $txAfterVat) * (1 + $txAfterVatWithout1), 'MT');
            }
            if ($localtax2Type2 && $localtax2TxPct > 0) {
                $lt2 = Price2Num::num($ttc - $ttc / (1 + $txAfterVat) * (1 + $txAfterVatWithout2), 'MT');
            }

            // amount with HT + taxes added before VAT
            $htPlusBeforeVat = $ttc - $lt1 - $lt2;

            if (!$localtax1Type2 && $localtax1TxPct > 0) {
                $lt1 = Price2Num::num(
                    $htPlusBeforeVat - $htPlusBeforeVat / (1 + $txBeforeVat) * (1 + $txBeforeVatWithout1),
                    'MT',
                );
            }
            if (!$localtax2Type2 && $localtax2TxPct > 0) {
                $lt2 = Price2Num::num(
                    $htPlusBeforeVat - $htPlusBeforeVat / (1 + $txBeforeVat) * (1 + $txBeforeVatWithout2),
                    'MT',
                );
            }

            $tva = ($ttc - $lt2 - $lt1) - ($ttc - $lt2 - $lt1) / (1 + $tvaTxPct);
            $totalTva = Price2Num::num($tva, 'MT');
            $totalLocaltax1 = $lt1;
            $totalLocaltax2 = $lt2;
            $totalHt = Price2Num::num($totalTtc - $totalLocaltax1 - $totalLocaltax2 - $totalTva, 'MT');

            $mcTotalTtc = Price2Num::num($amount * $multicurrencyTx, 'MT');
            $mcTotalHt = Price2Num::num($amount / (1 + $tvaTxPct) * $multicurrencyTx, 'MT');
            $mcTotalTva = Price2Num::num($mcTotalTtc - $mcTotalHt, 'MT');
        } elseif ($amountType === 0) {
            // HT
            $totalHt = Price2Num::num($amount, 'MT');
            $totalTva = Price2Num::num($totalHt * $tvaTxPct, 'MT');

            $mcTotalHt = Price2Num::num($amount * $multicurrencyTx, 'MT');
            $mcTotalTva = Price2Num::num($amount * $tvaTxPct * $multicurrencyTx, 'MT');

            $totalLocaltax1 = $localtax1Type2 === 0
                ? Price2Num::num($totalHt * $localtax1TxPct, 'MT')
                : Price2Num::num(($totalHt + $totalTva) * $localtax1TxPct, 'MT');
            $totalLocaltax2 = $localtax2Type2 === 0
                ? Price2Num::num($totalHt * $localtax2TxPct, 'MT')
                : Price2Num::num(($totalHt + $totalTva) * $localtax2TxPct, 'MT');
        }

        $discount->setAmountHt((float) $totalHt);
        $discount->setAmountTva((float) $totalTva);
        $discount->setAmountLocaltax1((float) ($totalLocaltax1 ?? 0));
        $discount->setAmountLocaltax2((float) ($totalLocaltax2 ?? 0));
        $discount->setAmountTtc((float) ($totalTtc ?? 0));
        $discount->setMulticurrencyAmountHt((float) ($mcTotalHt ?? 0));
        $discount->setMulticurrencyAmountTva((float) ($mcTotalTva ?? 0));
        $discount->setMulticurrencyAmountTtc((float) ($mcTotalTtc ?? 0));

        $discount->setTvaTx($tvaTx);
        $discount->setLocaltax1Tx($localtax1Tx);
        $discount->setLocaltax1Type((string) $localtax1Type);
        $discount->setLocaltax2Tx($localtax2Tx);
        $discount->setLocaltax2Type((string) $localtax2Type);

        if (empty($discount->getAmountTtc())) {
            // If the total amount comes from a split discount, take it as-is:
            // recomputing the total from the net amount creates precision errors.
            $recomputedTtc = Price2Num::num(
                $discount->getAmountHt() + $discount->getAmountTva()
                + $discount->getAmountLocaltax1() + $discount->getAmountLocaltax2(),
                'MT',
            );
            $discount->setAmountTtc($recomputedTtc);
            $discount->setMulticurrencyAmountTtc(Price2Num::num(
                ($discount->getAmountHt() + $discount->getAmountTva()
                    + $discount->getAmountLocaltax1() + $discount->getAmountLocaltax2()) * $multicurrencyTx,
                'MT',
            ));
        }
    }
}
