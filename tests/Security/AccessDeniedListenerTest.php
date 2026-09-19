<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\AccessDeniedListener;
use App\Security\ApiClientUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class AccessDeniedListenerTest extends TestCase
{
    public function testUnauthenticatedRequestIsLeftToTheEntryPoint(): void
    {
        $listener = new AccessDeniedListener(new TokenStorage());
        $event = $this->exceptionEvent(new AccessDeniedException());

        $listener->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testAuthenticatedRequestGetsUpstream403Shape(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new PostAuthenticationToken(new ApiClientUser('alice', 1), 'main', ['ROLE_API']));
        $listener = new AccessDeniedListener($storage);
        $event = $this->exceptionEvent(new AccessDeniedException());

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            ['error' => ['code' => 403, 'message' => 'Forbidden']],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testOtherExceptionsAreIgnored(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new PostAuthenticationToken(new ApiClientUser('alice', 1), 'main', ['ROLE_API']));
        $listener = new AccessDeniedListener($storage);
        $event = $this->exceptionEvent(new \RuntimeException('boom'));

        $listener->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function exceptionEvent(\Throwable $throwable): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ExceptionEvent($kernel, Request::create('/api'), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
