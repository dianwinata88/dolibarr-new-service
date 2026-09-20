<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Request-scoped Dolibarr context resolved from the authenticated API key.
 *
 * Inject this service anywhere (providers, processors, repositories) to get
 * the multicompany `entity` of the current request — the value every
 * `entity`-column filter must apply. Falls back to `1` (upstream master
 * entity default) when the request is unauthenticated (public endpoints,
 * console, fixtures).
 *
 * Same data is also reachable as the `_dolibarr_entity` / `_dolibarr_login`
 * request attributes set by ApiKeyAuthenticator.
 */
final class EntityContext
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getClient(): ?ApiClientUser
    {
        $user = $this->security->getUser();

        return $user instanceof ApiClientUser ? $user : null;
    }

    public function getEntity(): int
    {
        return $this->getClient()?->getEntity() ?? 1;
    }

    public function getLogin(): ?string
    {
        return $this->getClient()?->getLogin();
    }
}
