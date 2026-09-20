<?php

declare(strict_types=1);

namespace App\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * MariaDB/MySQL POINT spatial column (upstream llx_societe.geopoint,
 * llx_socpeople.geopoint). DBAL ships no spatial types, so this minimal type
 * keeps the real `point` DDL and lets schema introspection/validation work.
 * Values hydrate as the driver's raw (binary) representation.
 */
final class PointType extends Type
{
    public const NAME = 'point';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'point';
    }
}
