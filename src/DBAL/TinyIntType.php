<?php

declare(strict_types=1);

namespace App\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;

/**
 * Dolibarr uses bare `tinyint` columns as small integers with value ranges
 * beyond 0/1 (e.g. llx_societe.client is 0/1/2, cond_reglement codes, ...).
 * DBAL maps introspected tinyint to boolean, which would truncate values
 * like `2` to `true`; this type keeps integer semantics and emits `tinyint`
 * DDL so the physical schema stays identical to upstream.
 */
final class TinyIntType extends IntegerType
{
    public const NAME = 'tinyint';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'tinyint';
    }
}
