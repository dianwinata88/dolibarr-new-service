<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Resolves an API key from DOLIBARR_API_KEYS, which is either a JSON map
 * ("<key>": {"login": ..., "entity": int}) or a bare comma-separated list.
 */
final class TestApiKeys
{
    public static function first(): string
    {
        $raw = (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? '');
        $decoded = json_decode($raw, true);
        if (\is_array($decoded) && [] !== $decoded) {
            return (string) array_key_first($decoded);
        }

        $first = trim(explode(',', $raw)[0]);

        return '' !== $first ? $first : 'test-api-key';
    }
}
