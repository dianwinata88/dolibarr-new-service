<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Security\EntityContext;

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
    public function __construct(
        private readonly ?EntityContext $entityContext = null,
    ) {
    }

    /** $conf->entity — master entity of a single-entity install. */
    public function entity(): int
    {
        return $this->entityContext?->getClient()?->getEntity() ?? (int) (getenv('DOLIBARR_ENTITY') ?: '1');
    }

    /** DolibarrApiAccess::$user->id — id the API key maps to upstream. */
    public function userId(): int
    {
        return (int) (getenv('DOLIBARR_API_USER_ID') ?: '1');
    }
}
