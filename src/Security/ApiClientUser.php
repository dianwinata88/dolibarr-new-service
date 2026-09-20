<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Authenticated API client resolved from a Dolibarr-style API key.
 *
 * Mirrors the upstream concept of "the llx_user row behind the API key":
 * the key identifies a login and a multicompany `entity` that scopes every
 * query of the request (see App\Security\EntityContext).
 */
final class ApiClientUser implements UserInterface
{
    public function __construct(
        private readonly string $login,
        private readonly int $entity = 1,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->login;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Multicompany entity the key belongs to (upstream `llx_user.entity`,
     * `1` for the master entity).
     */
    public function getEntity(): int
    {
        return $this->entity;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void
    {
        // Stateless token auth — nothing to erase.
    }
}
