<?php

declare(strict_types=1);

namespace App\Aux;

use App\Entity\Societe;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * Write-through of the aux tables on company lifecycle, mirroring upstream
 * Societe side effects:
 *  - postUpdate: llx_societe_log row (company modify -> log row) and, when
 *    MAIN_COMPANY_PERENTITY_SHARED is on, the llx_societe_perentity row for
 *    the current entity (the accountancy block of Societe::update()).
 *  - preRemove: llx_societe_perentity rows when sharing is on
 *    (Societe::delete() removes them before the company row — preRemove
 *    also keeps the same ordering, and the rowid is still readable there).
 */
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class SocieteWriteThroughListener
{
    public function __construct(
        private readonly SocietePerEntityService $perEntity,
        private readonly SocieteLogWriter $logWriter,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Societe) {
            return;
        }
        $rowid = $entity->getRowid();
        if ($rowid === null) {
            return;
        }

        $this->logWriter->logModify($rowid, $entity->getFkStcomm());
        $this->perEntity->syncFromSociete($entity);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Societe) {
            return;
        }
        $rowid = $entity->getRowid();
        if ($rowid === null) {
            return;
        }

        $this->perEntity->deleteForThirdparty($rowid);
    }
}
