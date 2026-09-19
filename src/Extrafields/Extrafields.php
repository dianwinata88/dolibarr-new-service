<?php

declare(strict_types=1);

namespace App\Extrafields;

/**
 * Marks an entity as having an associated llx_*_extrafields table.
 *
 * Upstream Dolibarr stores extra field *values* in <object>_extrafields
 * tables joined on fk_object = <object>.rowid, with one column per defined
 * extra field. The column set is dynamic, so values are exposed as a plain
 * map on the object (mirroring $object->array_options upstream).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Extrafields
{
    public function __construct(
        public readonly string $table,
        public readonly string $joinColumn = 'fk_object',
    ) {
    }
}
