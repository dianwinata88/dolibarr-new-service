<?php

declare(strict_types=1);

namespace App\Extrafields;

/**
 * Default implementation of ExtrafieldsAwareInterface: holds the extra field
 * values as a non-persisted map on the entity. Populated on load by
 * ExtrafieldsListener and written back with ExtrafieldsWriter.
 */
trait ExtrafieldsAwareTrait
{
    /** @var array<string, mixed> */
    private array $extrafields = [];

    /**
     * @return array<string, mixed>
     */
    public function getExtrafields(): array
    {
        return $this->extrafields;
    }

    /**
     * @param array<string, mixed> $extrafields
     */
    public function setExtrafields(array $extrafields): static
    {
        $this->extrafields = $extrafields;

        return $this;
    }

    public function getExtrafield(string $name): mixed
    {
        return $this->extrafields[$name] ?? null;
    }

    public function setExtrafield(string $name, mixed $value): static
    {
        $this->extrafields[$name] = $value;

        return $this;
    }
}
