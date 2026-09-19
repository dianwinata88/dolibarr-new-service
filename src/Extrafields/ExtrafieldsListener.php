<?php

declare(strict_types=1);

namespace App\Extrafields;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

/**
 * Loads extra field values into ExtrafieldsAwareInterface entities after
 * hydration — mirrors upstream fetchOptionals()/array_options, where extra
 * fields always travel inline with the object.
 */
#[AsDoctrineListener(event: Events::postLoad)]
final class ExtrafieldsListener
{
    /** Columns that are never extra field values. */
    private const INTERNAL_COLUMNS = ['rowid', 'tms', 'fk_object', 'import_key'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ExtrafieldsMetadata $metadata,
    ) {
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ExtrafieldsAwareInterface) {
            return;
        }

        $extrafields = $this->metadata->for($entity::class);
        $rowid = $entity->getRowid();
        if ($extrafields === null || $rowid === null) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE %s = ?', $extrafields->table, $extrafields->joinColumn),
            [$rowid],
        );

        if ($row === false) {
            return;
        }

        foreach (self::INTERNAL_COLUMNS as $internal) {
            unset($row[$internal]);
        }

        $entity->setExtrafields($row);
    }
}
