<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\ApiClientUser;
use App\Security\ApiKeyAuthenticator;
use App\Security\DolibarrAuthenticationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ApiKeyAuthenticatorTest extends TestCase
{
    private const KEYS_JSON = '{"key-a":{"login":"alice","entity":1},"key-b":{"login":"bob","entity":2}}';

    private ApiKeyAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApiKeyAuthenticator(self::KEYS_JSON);
    }

    public function testSupportsAllUpstreamKeyLocations(): void
    {
        self::assertTrue($this->authenticator->supports(
            Request::create('/api', server: ['HTTP_DOLAPIKEY' => 'key-a']),
        ));
        self::assertTrue($this->authenticator->supports(
            Request::create('/api', server: ['HTTP_AUTHORIZATION' => 'Bearer key-a']),
        ));
        self::assertTrue($this->authenticator->supports(Request::create('/api?api_key=key-a')));
        self::assertTrue($this->authenticator->supports(Request::create('/api?DOLAPIKEY=key-a')));
    }

    public function testDoesNotSupportRequestWithoutKey(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/api')));
    }

    public function testHeaderWinsOverQueryParam(): void
    {
        $request = Request::create('/api?api_key=wrong', server: ['HTTP_DOLAPIKEY' => 'key-a']);
        $client = $this->authenticateClient($request);
        self::assertSame('alice', $client->getLogin());
    }

    public function testBearerIsOnlyAFallback(): void
    {
        // DOLAPIKEY header present: the bearer token must be ignored.
        $request = Request::create('/api', server: [
            'HTTP_DOLAPIKEY' => 'key-b',
            'HTTP_AUTHORIZATION' => 'Bearer key-a',
        ]);
        self::assertSame('bob', $this->authenticateClient($request)->getLogin());
    }

    public function testKeyResolvesLoginAndEntityScope(): void
    {
        $request = Request::create('/api', server: ['HTTP_DOLAPIKEY' => 'key-b']);
        $client = $this->authenticateClient($request);

        self::assertSame('bob', $client->getLogin());
        self::assertSame(2, $client->getEntity());
        self::assertContains('ROLE_API', $client->getRoles());
    }

    public function testBareCommaSeparatedKeysStillWork(): void
    {
        $authenticator = new ApiKeyAuthenticator('one,two , three');
        $request = Request::create('/api', server: ['HTTP_DOLAPIKEY' => 'two']);
        $client = $this->authenticateClient($request, $authenticator);

        self::assertSame('api-client', $client->getLogin());
        self::assertSame(1, $client->getEntity());
    }

    public function testUnknownKeyIsRejectedWith401(): void
    {
        $request = Request::create('/api', server: ['HTTP_DOLAPIKEY' => 'nope']);

        try {
            $this->authenticator->authenticate($request);
            self::fail('Expected DolibarrAuthenticationException');
        } catch (DolibarrAuthenticationException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertStringContainsString('Error user not valid', $e->getMessage());
        }
    }

    public function testDolCryptKeyIsRejectedWith503(): void
    {
        $request = Request::create('/api', server: ['HTTP_DOLAPIKEY' => 'dolcrypt:abc']);

        try {
            $this->authenticator->authenticate($request);
            self::fail('Expected DolibarrAuthenticationException');
        } catch (DolibarrAuthenticationException $e) {
            self::assertSame(503, $e->getStatusCode());
            self::assertStringContainsString('dolcrypt:', $e->getMessage());
        }
    }

    public function testDolApiEntityHeaderMustMatchKeyEntity(): void
    {
        $mismatch = Request::create('/api', server: [
            'HTTP_DOLAPIKEY' => 'key-a',
            'HTTP_DOLAPIENTITY' => '2',
        ]);

        try {
            $this->authenticator->authenticate($mismatch);
            self::fail('Expected DolibarrAuthenticationException');
        } catch (DolibarrAuthenticationException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertStringContainsString('Token not valid', $e->getMessage());
        }

        $match = Request::create('/api', server: [
            'HTTP_DOLAPIKEY' => 'key-a',
            'HTTP_DOLAPIENTITY' => '1',
        ]);
        self::assertSame('alice', $this->authenticateClient($match)->getLogin());
    }

    private function authenticateClient(Request $request, ?ApiKeyAuthenticator $authenticator = null): ApiClientUser
    {
        $passport = ($authenticator ?? $this->authenticator)->authenticate($request);
        $user = $passport->getBadge(UserBadge::class)->getUserLoader()();

        \assert($user instanceof ApiClientUser);

        return $user;
    }
}
