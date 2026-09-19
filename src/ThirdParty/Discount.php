<?php

declare(strict_types=1);

namespace App\ThirdParty;

/**
 * Data holder for the discount being computed (port of the DiscountAbsolute
 * fields touched by generateFromAmount/create).
 */
final class Discount
{
    public $fk_soc = null;
    public $socid = null;
    public $discount_type = 0;
    public $datec = null;
    public $description = null;
    public $vat_src_code = null;
    public $multicurrency_code = null;
    public $multicurrency_tx = 1.0;
    public $fk_multicurrency = null;
    public $fk_facture_source = null;
    public $fk_invoice_supplier_source = null;
    public $fk_facture = null;
    public $fk_facture_line = null;
    public $fk_invoice_supplier = null;
    public $fk_invoice_supplier_line = null;

    public $amount_ht = 0.0;
    public $amount_tva = 0.0;
    public $amount_ttc = 0.0;
    public $amount_localtax1 = 0.0;
    public $amount_localtax2 = 0.0;
    public $tva_tx = 0;
    public $localtax1_tx = 0.0;
    public $localtax1_type = '0';
    public $localtax2_tx = 0.0;
    public $localtax2_type = '0';

    public $total_ht = 0.0;
    public $total_tva = 0.0;
    public $total_ttc = 0.0;
    public $total_localtax1 = 0.0;
    public $total_localtax2 = 0.0;
    public $multicurrency_amount_ht = 0.0;
    public $multicurrency_amount_tva = 0.0;
    public $multicurrency_amount_ttc = 0.0;
    public $multicurrency_total_ht = 0.0;
    public $multicurrency_total_tva = 0.0;
    public $multicurrency_total_ttc = 0.0;

    /**
     * Port of DiscountAbsolute::generateFromAmount().
     */
    public function generateFromAmount(float $amount, int $amountType, float $tvaTx): int
    {
        $tvaTxPct = $tvaTx / 100;
        $localtax1TxPct = $this->localtax1_tx / 100;
        $localtax2TxPct = $this->localtax2_tx / 100;

        $localtax1Type2 = ((int) $this->localtax1_type > 0 && (int) $this->localtax1_type % 2 == 0) ? 1 : 0;
        $localtax2Type2 = ((int) $this->localtax2_type > 0 && (int) $this->localtax2_type % 2 == 0) ? 1 : 0;

        $p = fn ($v) => (float) $this->price2num((float) $v, 'MT');

        if ($amountType == 1) {
            // TTC
            $this->total_ttc = $p($amount);
            $ttc = $this->total_ttc;

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
                $lt1 = $p($ttc - $ttc / (1 + $txAfterVat) * (1 + $txAfterVatWithout1));
            }
            if ($localtax2Type2 && $localtax2TxPct > 0) {
                $lt2 = $p($ttc - $ttc / (1 + $txAfterVat) * (1 + $txAfterVatWithout2));
            }

            $htplusbeforevat = $ttc - $lt1 - $lt2;

            if (!$localtax1Type2 && $localtax1TxPct > 0) {
                $lt1 = $p($htplusbeforevat - $htplusbeforevat / (1 + $txBeforeVat) * (1 + $txBeforeVatWithout1));
            }
            if (!$localtax2Type2 && $localtax2TxPct > 0) {
                $lt2 = $p($htplusbeforevat - $htplusbeforevat / (1 + $txBeforeVat) * (1 + $txBeforeVatWithout2));
            }

            $tva = ($ttc - $lt2 - $lt1) - ($ttc - $lt2 - $lt1) / (1 + $tvaTxPct);
            $this->total_tva = $p($tva);
            $this->total_localtax1 = $lt1;
            $this->total_localtax2 = $lt2;
            $this->total_ht = $p($this->total_ttc - $this->total_localtax1 - $this->total_localtax2 - $this->total_tva);

            $this->multicurrency_total_ttc = $p($amount * $this->multicurrency_tx);
            $this->multicurrency_total_ht = $p($amount / (1 + $tvaTxPct) * $this->multicurrency_tx);
            $this->multicurrency_total_tva = $p($this->multicurrency_total_ttc - $this->multicurrency_total_ht);
        } elseif ($amountType == 0) {
            // HT
            $this->total_ht = $p($amount);
            $this->total_tva = $p($this->total_ht * $tvaTxPct);

            $this->multicurrency_total_ht = $p($amount * $this->multicurrency_tx);
            $this->multicurrency_total_tva = $p($amount * $tvaTxPct * $this->multicurrency_tx);

            if ($localtax1Type2 == 0) {
                $this->total_localtax1 = $p($this->total_ht * $localtax1TxPct);
            } else {
                $this->total_localtax1 = $p(($this->total_ht + $this->total_tva) * $localtax1TxPct);
            }
            if ($localtax2Type2 == 0) {
                $this->total_localtax2 = $p($this->total_ht * $localtax2TxPct);
            } else {
                $this->total_localtax2 = $p(($this->total_ht + $this->total_tva) * $localtax2TxPct);
            }
        }

        // backward compatibility
        $this->amount_ht = $this->total_ht;
        $this->amount_tva = $this->total_tva;
        $this->amount_ttc = $this->total_ttc;
        $this->multicurrency_amount_ht = $this->multicurrency_total_ht;
        $this->multicurrency_amount_tva = $this->multicurrency_total_tva;
        $this->multicurrency_amount_ttc = $this->multicurrency_total_ttc;

        if (empty($this->total_ttc)) {
            $this->total_ttc = $p($this->total_ht + $this->total_tva + $this->total_localtax1 + $this->total_localtax2);
            $this->multicurrency_total_ttc = $p(($this->total_ht + $this->total_tva + $this->total_localtax1 + $this->total_localtax2) * $this->multicurrency_tx);
            $this->amount_ttc = $this->total_ttc;
            $this->multicurrency_amount_ttc = $this->multicurrency_total_ttc;
        }

        return 0;
    }

    private function price2num(float $value, string $rounding): string
    {
        $nbdecimals = $rounding === 'MT' ? 2 : 5;

        return (string) round($value, $nbdecimals);
    }
}
