<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Renders ApiErrorException like upstream DolibarrApi errors:
 * {"error": {"code": <http-status>, "message": "...", ...extra}}.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$e instanceof ApiErrorException) {
            return;
        }
        $event->setResponse(new JsonResponse($e->toBody(), $e->getStatusCode()));
    }
}
