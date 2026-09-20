<?php

declare(strict_types=1);

namespace App\Category;

/**
 * Turns a raw llx_categorie row into the response array json_encode'd by
 * upstream — i.e. get_object_vars() of the Categorie object minus the keys
 * removed by _cleanObjectDatas() (api.class.php + Categories::_cleanObjectDatas).
 */
final class CategorySerializer
{
    public function __construct(private readonly CategoryService $service)
    {
    }

    /**
     * @param array<string, mixed>          $row         raw llx_categorie row
     * @param array<int, array<string,mixed>> $childRows  cleaned child rows (only when include_childs)
     * @return array<string, mixed>
     */
    public function toArray(array $row, array $childRows = []): array
    {
        $arrayOptions = [];
        foreach ($this->service->fetchExtrafields((int) $row['rowid']) as $col => $value) {
            $arrayOptions['options_' . $col] = $value;
        }

        return [
            'id' => (int) $row['rowid'],
            'entity' => (int) $row['entity'],
            'fk_parent' => (int) $row['fk_parent'],
            'label' => $row['label'],
            'description' => $row['description'],
            'color' => $row['color'],
            'position' => (int) ($row['position'] ?? 0),
            'socid' => (int) ($row['fk_soc'] ?? 0),
            'visible' => (int) $row['visible'],
            'type' => (int) $row['type'],
            'ref_ext' => $row['ref_ext'],
            'import_key' => $row['import_key'],
            'date_creation' => $this->timestamp($row['date_creation'] ?? null),
            'date_modification' => $this->timestamp($row['tms'] ?? null),
            'user_creation_id' => (int) ($row['fk_user_creat'] ?? 0),
            'user_modification_id' => (int) ($row['fk_user_modif'] ?? 0),
            'array_options' => $arrayOptions,
            'multilangs' => [],
            'childs' => $childRows,
        ];
    }

    /**
     * Port of _filterObjectProperties(): when $properties is a comma list,
     * keep only those keys.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterProperties(array $data, string $properties): array
    {
        if ($properties === '') {
            return $data;
        }
        $keep = array_map('trim', explode(',', $properties));

        return array_intersect_key($data, array_flip($keep));
    }

    /** Upstream jdate() output: unix timestamp, null for empty values. */
    private function timestamp(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        $ts = strtotime((string) $value);

        return $ts === false ? null : $ts;
    }
}
