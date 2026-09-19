<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-level coverage of the Dolibarr-compatible API key flow against the
 * real firewall. Keys are resolved from the live DOLIBARR_API_KEYS env var
 * (a JSON map, or a bare comma-separated list mapped to entity 1), so the
 * tests work with the committed .env dev defaults as well as a custom set.
 */
final class ApiAuthFunctionalTest extends WebTestCase
{
    public function testMissingCredentialsReturnUpstream401Shape(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api');

        self::assertResponseStatusCodeSame(401);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(401, $body['error']['code']);
        self::assertStringContainsString('Failed to login to API', $body['error']['message']);
    }

    public function testDolApiKeyHeaderAuthenticates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_DOLAPIKEY' => self::apiKeyForEntity(1)]);

        self::assertResponseIsSuccessful();
    }

    public function testApiKeyQueryParamAuthenticates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', parameters: ['api_key' => self::apiKeyForEntity(1)]);

        self::assertResponseIsSuccessful();
    }

    public function testDolApiKeyQueryParamAuthenticates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', parameters: ['DOLAPIKEY' => self::apiKeyForEntity(1)]);

        self::assertResponseIsSuccessful();
    }

    public function testBearerTokenAuthenticates(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . self::apiKeyForEntity(1)]);

        self::assertResponseIsSuccessful();
    }

    public function testWrongKeyReturnsUpstream401Shape(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_DOLAPIKEY' => 'wrong-key']);

        self::assertResponseStatusCodeSame(401);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(401, $body['error']['code']);
        self::assertStringContainsString('Error user not valid', $body['error']['message']);
    }

    public function testDolCryptKeyReturns503(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_DOLAPIKEY' => 'dolcrypt:abc']);

        self::assertResponseStatusCodeSame(503);
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(503, $body['error']['code']);
    }

    public function testDolApiEntityMatchingKeyIsAccepted(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: [
            'HTTP_DOLAPIKEY' => self::apiKeyForEntity(1),
            'HTTP_DOLAPIENTITY' => '1',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testDolApiEntityMismatchingKeyIsRejected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: [
            'HTTP_DOLAPIKEY' => self::apiKeyForEntity(1),
            'HTTP_DOLAPIENTITY' => '2',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testSecondEntityKeyAuthenticates(): void
    {
        $key = self::apiKeyForEntity(2);
        if (null === $key) {
            self::markTestSkipped('No entity-2 key configured in DOLIBARR_API_KEYS');
        }

        $client = self::createClient();
        $client->request('GET', '/api', server: [
            'HTTP_DOLAPIKEY' => $key,
            'HTTP_DOLAPIENTITY' => '2',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testHealthzStaysPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
    }

    private static function apiKeyForEntity(int $entity): ?string
    {
        foreach (self::apiKeys() as $key => $info) {
            if (($info['entity'] ?? 1) === $entity) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, array{entity: int}> */
    private static function apiKeys(): array
    {
        $raw = (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? '{"dolibarr-dev-key":{"entity":1}}');
        $decoded = json_decode($raw, true);
        if (\is_array($decoded)) {
            return $decoded;
        }

        $keys = [];
        foreach (explode(',', $raw) as $key) {
            $key = trim($key);
            if ('' !== $key) {
                $keys[$key] = ['entity' => 1];
            }
        }

        return $keys;
    }
}
