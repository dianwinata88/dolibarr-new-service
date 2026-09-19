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
        $keys = explode(',', (string) ($_SERVER['DOLIBARR_API_KEYS'] ?? 'dolibarr-dev-key'));
        $client->request('GET', '/api', server: ['HTTP_DOLAPIKEY' => trim($keys[0])]);

        self::assertResponseIsSuccessful();
    }
}
