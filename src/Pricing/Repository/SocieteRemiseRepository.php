<?php

declare(strict_types=1);

namespace App\Pricing\Repository;

use App\Entity\SocieteRemise;
use App\Pricing\DolibarrContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Queries for llx_societe_remise — the customer relative-discount history.
 *
 * Upstream read path: Societe::fetch() LEFT JOINs the latest history row
 * (MAX(rowid) scoped to entity IN (getEntity('discount'))) to compute
 * remise_percent; write path is Societe::set_remise_client().
 *
 * @extends ServiceEntityRepository<SocieteRemise>
 */
final class SocieteRemiseRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DolibarrContext $context,
    ) {
        parent::__construct($registry, SocieteRemise::class);
    }

    /**
     * remise_percent: remise_client of the latest history row in the current
     * entity, or 0 when the thirdparty has no history (upstream behaviour —
     * a NULL LEFT JOIN column becomes 0).
     */
    public function latestRemiseClient(int $socid): float
    {
        $row = $this->createQueryBuilder('r')
            ->orderBy('r.rowid', 'DESC')
            ->setMaxResults(1)
            ->where('r.fk_soc = :socid')
            ->andWhere('r.entity = :entity')
            ->setParameter('socid', $socid)
            ->setParameter('entity', $this->context->entity())
            ->getQuery()
            ->getOneOrNullResult();

        return $row instanceof SocieteRemise ? (float) $row->getRemiseClient() : 0.0;
    }

    /**
     * @return list<SocieteRemise>
     */
    public function history(int $socid): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.fk_soc = :socid')
            ->andWhere('r.entity = :entity')
            ->setParameter('socid', $socid)
            ->setParameter('entity', $this->context->entity())
            ->orderBy('r.rowid', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
