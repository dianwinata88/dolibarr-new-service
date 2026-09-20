<?php

declare(strict_types=1);

namespace App\Extrafields;

/**
 * Implemented by entities whose rows can carry extra field values stored in
 * a companion llx_*_extrafields table (see #[Extrafields]).
 *
 * Extra field values are exposed as an associative array keyed by the extra
 * column name, matching how upstream surfaces them on the object payload
 * (array_options / "extrafields" inline on API responses).
 */
interface ExtrafieldsAwareInterface
{
    /**
     * All currently loaded extra field values (column name => raw value).
     *
     * @return array<string, mixed>
     */
    public function getExtrafields(): array;

    /**
     * @param array<string, mixed> $extrafields
     */
    public function setExtrafields(array $extrafields): static;

    public function getExtrafield(string $name): mixed;

    public function setExtrafield(string $name, mixed $value): static;

    /**
     * The object's primary key — the value the extrafields row joins on
     * (fk_object = rowid upstream).
     */
    public function getRowid(): ?int;
}
