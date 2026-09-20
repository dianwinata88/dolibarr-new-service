<?php

declare(strict_types=1);

namespace App\Pricing\Repository;

use App\Entity\SocieteRemiseExcept;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Queries for llx_societe_remise_except that mirror the raw SQL in
 * htdocs/societe/class/api_thirdparties.class.php.
 *
 * llx_facture / llx_facture_fourn are not ported to this service, so the
 * LEFT JOIN columns (ref, factype) are returned as NULL and the supplier
 * "mode" keeps the upstream inner-join quirk (only rows with
 * fk_invoice_supplier_source set are returned).
 *
 * @extends ServiceEntityRepository<SocieteRemiseExcept>
 */
final class SocieteRemiseExceptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocieteRemiseExcept::class);
    }

    /**
     * api_thirdparties::getFixedAmountDiscounts().
     *
     * @param 'customer'|'supplier' $mode
     * @param 'none'|'available'|'used' $filter
     * @return list<array<string, string|int|float|null>>
     */
    public function findFixedAmountDiscounts(
        int $socid,
        string $mode,
        string $filter,
        string $sortfield = '',
        string $sortorder = 'ASC',
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        $fields = match ($mode) {
            'customer' => 're.fk_facture_source, re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc,'
                . ' re.description, re.fk_facture, re.fk_facture_line',
            'supplier' => 're.fk_invoice_supplier_source, re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc,'
                . ' re.description, re.fk_invoice_supplier, re.fk_invoice_supplier_line',
            default => throw new \InvalidArgumentException('Unknown mode'),
        };

        // No llx_facture / llx_facture_fourn table: the source invoice columns
        // come back NULL instead of the upstream LEFT JOIN values.
        $sql = 'SELECT NULL AS ref, NULL AS factype, ' . $fields
            . ' FROM llx_societe_remise_except AS re'
            . ' WHERE re.fk_soc = ' . $socid;

        if ($mode === 'supplier') {
            // Upstream's WHERE f.rowid = re.fk_invoice_supplier_source turns the
            // LEFT JOIN into an inner join — same effective filter.
            $sql .= ' AND re.fk_invoice_supplier_source IS NOT NULL';
            if ($filter === 'available') {
                $sql .= ' AND re.fk_invoice_supplier IS NULL AND re.fk_invoice_supplier_line IS NULL';
            }
            if ($filter === 'used') {
                $sql .= ' AND (re.fk_invoice_supplier IS NOT NULL OR re.fk_invoice_supplier_line IS NOT NULL)';
            }
        } else {
            if ($filter === 'available') {
                $sql .= ' AND re.fk_facture IS NULL AND re.fk_facture_line IS NULL';
            }
            if ($filter === 'used') {
                $sql .= ' AND (re.fk_facture IS NOT NULL OR re.fk_facture_line IS NOT NULL)';
            }
        }

        $sql .= $this->orderClause($sortfield, $sortorder, $mode);

        return $conn->fetchAllAssociative($sql);
    }

    /**
     * Rows matching a list of rowids — the splitdiscount() return shape
     * (customer-mode columns, same NULL ref/factype as the list endpoint).
     *
     * @param list<int> $rowids
     * @return list<array<string, string|int|float|null>>
     */
    public function findDiscountRows(array $rowids, int $socid): array
    {
        $conn = $this->getEntityManager()->getConnection();

        return $conn->fetchAllAssociative(
            'SELECT NULL AS ref, NULL AS factype, re.fk_facture_source, re.rowid, re.amount_ht,'
            . ' re.amount_tva, re.amount_ttc, re.description, re.fk_facture, re.fk_facture_line'
            . ' FROM llx_societe_remise_except AS re'
            . ' WHERE re.rowid IN (:ids) AND re.fk_soc = :socid'
            . ' ORDER BY re.rowid ASC',
            ['ids' => $rowids, 'socid' => $socid],
            ['ids' => ArrayParameterType::INTEGER],
        );
    }

    /**
     * $this->db->order($sortfield, $sortorder) equivalent — sanitizes field
     * names instead of interpolating raw input into SQL.
     */
    private function orderClause(string $sortfield, string $sortorder, string $mode): string
    {
        if ($sortfield === '') {
            return '';
        }

        $allowed = match ($mode) {
            'supplier' => [
                'rowid' => 're.rowid',
                'amount_ht' => 're.amount_ht',
                'amount_tva' => 're.amount_tva',
                'amount_ttc' => 're.amount_ttc',
                'description' => 're.description',
                'fk_invoice_supplier' => 're.fk_invoice_supplier',
                'fk_invoice_supplier_line' => 're.fk_invoice_supplier_line',
                'fk_invoice_supplier_source' => 're.fk_invoice_supplier_source',
                'f.type' => 're.rowid',
                'f.ref' => 're.rowid',
            ],
            default => [
                'rowid' => 're.rowid',
                'amount_ht' => 're.amount_ht',
                'amount_tva' => 're.amount_tva',
                'amount_ttc' => 're.amount_ttc',
                'description' => 're.description',
                'fk_facture' => 're.fk_facture',
                'fk_facture_line' => 're.fk_facture_line',
                'fk_facture_source' => 're.fk_facture_source',
                'f.type' => 're.rowid',
                'f.ref' => 're.rowid',
            ],
        };

        $orders = [];
        $fields = array_map(trim(...), explode(',', $sortfield));
        $dirs = array_map(
            static fn (string $d): string => strtoupper(trim($d)) === 'DESC' ? 'DESC' : 'ASC',
            explode(',', $sortorder),
        );

        foreach ($fields as $i => $field) {
            if (!isset($allowed[$field])) {
                continue;
            }
            $orders[] = $allowed[$field] . ' ' . ($dirs[$i] ?? $dirs[0] ?? 'ASC');
        }

        return $orders === [] ? '' : ' ORDER BY ' . implode(', ', $orders);
    }
}
