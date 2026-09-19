<?php

declare(strict_types=1);

namespace App\Pricing\Repository;

use App\Entity\SocietePrices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Queries for llx_societe_prices — the price-level change log written by
 * Societe::setPriceLevel(). Upstream table has no entity column.
 *
 * @extends ServiceEntityRepository<SocietePrices>
 */
final class SocietePricesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocietePrices::class);
    }

    /**
     * @return list<SocietePrices>
     */
    public function history(int $socid): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.fk_soc = :socid')
            ->setParameter('socid', $socid)
            ->orderBy('p.rowid', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
