<?php

declare(strict_types=1);

namespace App\BankAccount\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\BankAccount\ApiResource\BankAccountResource;
use App\BankAccount\CompanyBankAccountManager;
use App\BankAccount\CompanyBankAccountMapper;
use App\Entity\SocieteRib;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Port of upstream GET /thirdparties/{id}/bankaccounts
 * (api_thirdparties.class.php::getCompanyBankAccount).
 */
final class BankAccountCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly CompanyBankAccountManager $manager,
        private readonly CompanyBankAccountMapper $mapper,
    ) {
    }

    /**
     * @return BankAccountResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $socId = (int) ($uriVariables['socid'] ?? 0);
        if ($socId <= 0) {
            throw new BadRequestHttpException('Thirdparty ID is mandatory');
        }

        $ribs = $this->manager->listForSociete($socId);
        if ($ribs === []) {
            throw new NotFoundHttpException('Account not found');
        }

        return array_map(
            fn (SocieteRib $rib) => $this->mapper->toResource($rib, true),
            $ribs,
        );
    }
}
