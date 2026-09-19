<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Renders authorization failures in the upstream Dolibarr JSON error shape
 * ({"error": {"code": 403, "message": "Forbidden"}}) instead of Symfony's
 * default error page.
 *
 * Registered on kernel.exception at priority 5, before the firewall's own
 * exception listener (priority 1). When the request is unauthenticated it
 * steps aside so the firewall's entry point keeps producing the 401 shape.
 */
final class AccessDeniedListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /** @return array<string, list<mixed>> */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 5]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof AccessDeniedException && !$exception instanceof AccessDeniedHttpException) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            // Not authenticated: the firewall's entry point answers 401.
            return;
        }

        $event->setResponse(ApiError::response(403, 'Forbidden'));
    }
}
