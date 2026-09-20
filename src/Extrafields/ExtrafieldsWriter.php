<?php

declare(strict_types=1);

namespace App\Extrafields;

use Doctrine\DBAL\Connection;

/**
 * Persists extra field values for an ExtrafieldsAwareInterface entity.
 *
 * Mirrors upstream ExtraFields::insertExtraFields(): the existing
 * extrafields row is deleted and re-inserted with the current values, inside
 * a transaction. Only keys matching real columns of the extrafields table
 * are written; unknown keys are ignored (upstream ignores unknown field
 * names the same way). Call this after flushing the owning entity — or let
 * your API Platform processor call it.
 */
final class ExtrafieldsWriter
{
    /** Columns that are never extra field values. */
    private const INTERNAL_COLUMNS = ['rowid', 'tms', 'fk_object', 'import_key'];

    /** @var array<string, list<string>> table => extra column names */
    private array $columnCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ExtrafieldsMetadata $metadata,
    ) {
    }

    public function save(ExtrafieldsAwareInterface $object): void
    {
        $extrafields = $this->metadata->for($object::class);
        if ($extrafields === null) {
            throw new \LogicException(sprintf('%s is missing the #[Extrafields] attribute', $object::class));
        }

        $rowid = $object->getRowid();
        if ($rowid === null) {
            throw new \LogicException('Cannot persist extrafields for an entity without a rowid — flush it first');
        }

        $values = array_intersect_key($object->getExtrafields(), array_flip($this->extraColumns($extrafields->table)));

        $this->connection->transactional(function (Connection $connection) use ($extrafields, $rowid, $values): void {
            $connection->delete($extrafields->table, [$extrafields->joinColumn => $rowid]);
            if ($values !== []) {
                $connection->insert($extrafields->table, [$extrafields->joinColumn => $rowid] + $values);
            }
        });
    }

    /**
     * Column names of the extrafields table that hold extra field values
     * (i.e. every column except the internal bookkeeping ones).
     *
     * @return list<string>
     */
    private function extraColumns(string $table): array
    {
        if (!isset($this->columnCache[$table])) {
            $columns = array_map(
                static fn ($column): string => $column->getName(),
                $this->connection->createSchemaManager()->listTableColumns($table),
            );
            $this->columnCache[$table] = array_values(array_diff($columns, self::INTERNAL_COLUMNS));
        }

        return $this->columnCache[$table];
    }
}
