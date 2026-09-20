<?php

declare(strict_types=1);

namespace App\BankAccount\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use App\BankAccount\CompanyBankAccountManager;
use App\BankAccount\CompanyBankAccountMapper;
use App\BankAccount\FieldSanitizer;
use App\Entity\Societe;
use App\Entity\SocieteRib;
use App\Security\EntityContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Port of upstream POST/PUT/DELETE /thirdparties/{id}/bankaccounts[/{rib}]
 * (api_thirdparties.class.php::createCompanyBankAccount,
 * updateCompanyBankAccount, deleteCompanyBankAccount).
 *
 * Request fields are applied like upstream: every submitted key is sanitized
 * (POST through the 'extrafields' bug -> alphanohtml for every scalar, PUT
 * with per-field types), 'caller' is skipped, then the fixed writable
 * projection of CompanyBankAccount::update() decides what persists.
 */
final class BankAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompanyBankAccountManager $manager,
        private readonly CompanyBankAccountMapper $mapper,
        private readonly FieldSanitizer $sanitizer,
        private readonly RequestStack $requestStack,
        private readonly EntityContext $entityContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $socId = (int) ($uriVariables['socid'] ?? 0);
        $ribId = (int) ($uriVariables['id'] ?? 0);

        if ($operation instanceof Post) {
            return $this->create($socId);
        }
        if ($operation instanceof Put) {
            return $this->update($socId, $ribId);
        }
        if ($operation instanceof Delete) {
            return $this->remove($socId, $ribId);
        }

        throw new \LogicException('Unsupported operation for bank accounts');
    }

    private function create(int $socId): object
    {
        $company = $this->fetchCompany($socId);

        $props = [];
        foreach ($this->requestData() as $field => $value) {
            if ($field === 'caller') {
                continue;
            }
            if (in_array($field, ['id', 'rowid'], true)) {
                // upstream would let 'id' corrupt the UPDATE WHERE clause
                continue;
            }
            // upstream quirk: every field goes through 'extrafields' -> alphanohtml
            $props[$field] = $this->sanitizer->sanitizeForCreate($value);
        }

        $rib = new SocieteRib();
        // upstream honors request 'socid'/'type'/'datec' on the INSERT
        $rib->setFkSoc((int) ($props['socid'] ?? $socId));
        $rib->setType((string) ($props['type'] ?? 'ban'));
        if (isset($props['datec']) && is_string($props['datec'])) {
            $ts = strtotime($props['datec']);
            if ($ts !== false) {
                $rib->setDatec((new \DateTime())->setTimestamp($ts));
            }
        }
        $rib->setDefaultRib((int) ($props['default_rib'] ?? 0));

        // One transaction for insert + RUM + update projection: a projection
        // failure must not leave the minimal row behind.
        $this->em->wrapInTransaction(function () use ($rib, $company, $props): void {
            // CompanyBankAccount::create()
            $this->guardWrite(fn () => $this->manager->insertNew($rib), 'Error creating Company Bank account');

            // auto-generated RUM mandate when the request did not provide one
            $this->manager->fillRumIfEmpty($rib, $company->getCodeClient(), $props['rum'] ?? null);

            // CompanyBankAccount::update() projection over the request props
            $bag = array_merge($this->manager->blankProps(), $props);
            $bag['rum'] = $rib->getRum();
            $bag['date_rum'] = $rib->getDateRum();
            $bag['model_pdf'] = $rib->getModelPdf();
            $bag['default_rib'] = $rib->getDefaultRib();
            $this->guardWrite(fn () => $this->manager->applyUpdateProjection($rib, $bag), 'Error updating values');
        });

        return $this->mapper->toResource($rib, false);
    }

    private function update(int $socId, int $ribId): object
    {
        $company = $this->fetchCompany($socId);

        // upstream fetch() is by rowid only, then compares socid
        $rib = $this->manager->find($ribId);
        if ($rib === null || $rib->getFkSoc() !== $socId) {
            throw new AccessDeniedHttpException();
        }

        $props = [];
        foreach ($this->requestData() as $field => $value) {
            if ($field === 'caller') {
                continue;
            }
            if (in_array($field, ['id', 'rowid', 'socid', 'fk_soc'], true)) {
                // upstream would let 'id' corrupt the UPDATE WHERE clause
                continue;
            }
            $props[$field] = $this->sanitizer->sanitizeForUpdate($field, $value);
        }

        $bag = array_merge($this->manager->readProps($rib), $props);

        // auto-generated RUM mandate when the effective prop is empty
        $this->manager->fillRumIfEmpty($rib, $company->getCodeClient(), $bag['rum']);
        $bag['rum'] = $rib->getRum();
        $bag['date_rum'] = $rib->getDateRum();

        $this->guardWrite(fn () => $this->manager->applyUpdateProjection($rib, $bag), 'Error updating values');

        return $this->mapper->toResource($rib, true);
    }

    private function remove(int $socId, int $ribId): JsonResponse
    {
        // upstream deleteCompanyBankAccount: no company fetch, rowid lookup only
        $rib = $this->manager->find($ribId);
        $ribSocId = $rib === null ? 0 : (int) $rib->getFkSoc();

        if ($rib !== null && $ribSocId === $socId) {
            // upstream returns the int result of CompanyBankAccount::delete()
            return new JsonResponse($this->manager->delete($rib));
        }

        throw new AccessDeniedHttpException('Not allowed due to bad consistency of input data');
    }

    private function fetchCompany(int $socId): Societe
    {
        $company = $this->em->getRepository(Societe::class)->findOneBy(['rowid' => $socId, 'entity' => $this->entityContext->getEntity()]);
        if (!$company instanceof Societe) {
            throw new NotFoundHttpException("Error creating Company Bank account, Company doesn't exists");
        }

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $body = $request?->getContent();
        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function guardWrite(callable $write, string $message): void
    {
        try {
            $write();
        } catch (\Throwable $e) {
            // upstream throws RestException(500) on failed create()/update()
            throw new HttpException(500, $message, $e);
        }
    }
}
