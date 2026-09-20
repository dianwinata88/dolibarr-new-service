<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Normalizes plain HttpExceptions thrown on /api/* paths to the upstream
 * Dolibarr error shape {"error":{"code":<status>,"message":"..."}} — e.g.
 * NotFoundHttpException raised by API Platform providers, which would
 * otherwise render a Hydra {"@type":"Error"} body (with a stack trace in
 * dev) instead of the Restler format.
 *
 * Priority 10 keeps it below the ApiExceptionListener slices (also 10, but
 * they set the response first for ApiErrorException and we skip when a
 * response is already set) and ahead of API Platform's own error listener.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class HttpExceptionNormalizer
{
    public function __invoke(ExceptionEvent $event): void
    {
        if ($event->hasResponse()) {
            return;
        }
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }
        $e = $event->getThrowable();
        if (!$e instanceof HttpExceptionInterface) {
            return;
        }
        $code = $e->getStatusCode();
        $message = $e->getMessage() !== '' ? $e->getMessage() : 'Error';
        $event->setResponse(ApiError::response($code, $message));
    }
}
