<?php

declare(strict_types=1);

namespace App\Extrafields;

/**
 * Resolves and caches the #[Extrafields] attribute of an entity class.
 *
 * @internal used by ExtrafieldsListener and ExtrafieldsWriter
 */
final class ExtrafieldsMetadata
{
    /** @var array<class-string, Extrafields|null> */
    private array $cache = [];

    /**
     * @param class-string $class
     */
    public function for(string $class): ?Extrafields
    {
        if (!\array_key_exists($class, $this->cache)) {
            $attributes = (new \ReflectionClass($class))->getAttributes(Extrafields::class);
            $this->cache[$class] = $attributes === [] ? null : $attributes[0]->newInstance();
        }

        return $this->cache[$class];
    }
}
