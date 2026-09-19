<?php

declare(strict_types=1);

namespace App\ApiPlatform;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Contact;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\License;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Presents the generated OpenAPI document as the Dolibarr-compatible CRM API
 * (title, description, license), so the spec at /api/docs matches what
 * upstream API clients expect.
 */
#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final class DolibarrOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        #[Autowire(service: 'App\ApiPlatform\DolibarrOpenApiFactory.inner')]
        private readonly OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $info = new Info(
            title: 'Dolibarr CRM API',
            description: 'Dolibarr-compatible REST API for the CRM domain (third parties, contacts, '
                . 'categories), extracted from the Dolibarr ERP monolith as a standalone microservice. '
                . 'Authenticate with the `DOLAPIKEY` header, the `api_key` query parameter, or '
                . '`Authorization: Bearer <key>`.',
            version: '' !== $openApi->getInfo()->getVersion() ? $openApi->getInfo()->getVersion() : '1.0.0',
            contact: new Contact(name: 'Dolibarr CRM service'),
            license: new License(name: 'GPL-3.0-or-later', url: 'https://www.gnu.org/licenses/gpl-3.0.html'),
        );

        return $openApi->withInfo($info)->withExtensionProperty('x-dolibarr-compatible', true);
    }
}
