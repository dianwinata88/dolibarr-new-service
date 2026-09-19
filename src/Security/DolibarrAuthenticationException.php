<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Authentication failure carrying the HTTP status code upstream Dolibarr
 * returns for it (RestException code): 401 for auth problems, 503 for a
 * `dolcrypt:`-prefixed key.
 */
final class DolibarrAuthenticationException extends AuthenticationException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getMessageKey(): string
    {
        return $this->getMessage();
    }
}
