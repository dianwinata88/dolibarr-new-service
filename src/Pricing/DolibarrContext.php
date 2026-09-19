<?php

declare(strict_types=1);

namespace App\Pricing;

/**
 * Values upstream Dolibarr pulls from the global $conf object / API user.
 *
 * This microservice is single-entity (entity = 1, the master entity) and
 * authenticates with a static API key, so there is no real User object behind
 * a request. fk_user / fk_user_author columns are filled with
 * DOLIBARR_API_USER_ID (default 1, the admin rowid in a fresh Dolibarr
 * install).
 */
final class DolibarrContext
{
    /** $conf->entity — master entity of a single-entity install. */
    public function entity(): int
    {
        return (int) (getenv('DOLIBARR_ENTITY') ?: '1');
    }

    /** DolibarrApiAccess::$user->id — id the API key maps to upstream. */
    public function userId(): int
    {
        return (int) (getenv('DOLIBARR_API_USER_ID') ?: '1');
    }

    /**
     * getDolGlobalInt('PRODUIT_MULTIPRICES_LIMIT') — set to 5 by upstream
     * when the multiprices feature is enabled.
     */
    public function multipricesLimit(): int
    {
        return (int) (getenv('DOLIBARR_MULTIPRICES_LIMIT') ?: '5');
    }

    /**
     * getDolGlobalString('PRODUIT_MULTIPRICES')
     * || getDolGlobalString('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES').
     */
    public function multipricesEnabled(): bool
    {
        return (bool) (getenv('DOLIBARR_MULTIPRICES') ?: '1');
    }
}
