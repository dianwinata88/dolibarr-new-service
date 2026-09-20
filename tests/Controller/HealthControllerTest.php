<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthzReturnsOk(): void
    {
        $client = self::createClient();
        $client->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","service":"dolibarr-crm"}',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testApiEntrypointRequiresApiKey(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiEntrypointAcceptsDolApiKeyHeader(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_DOLAPIKEY' => self::firstConfiguredApiKey()]);

        self::assertResponseIsSuccessful();
    }

    private static function firstConfiguredApiKey(): string
    {
        $raw = (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? 'dolibarr-dev-key');
        $decoded = json_decode($raw, true);
        if (\is_array($decoded) && [] !== $decoded) {
            return (string) array_key_first($decoded);
        }

        return trim(explode(',', $raw)[0]);
    }
}
