<?php

declare(strict_types=1);

namespace App\SalesRep;

use App\Aux\ApiErrorException;
use App\Aux\DolibarrContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of the representative endpoints of
 * htdocs/societe/class/api_thirdparties.class.php.
 *
 * Upstream permission checks (hasRight('societe', 'lire'|'creer'|'supprimer'))
 * are satisfied by the API-key firewall: every authenticated request maps to
 * a full-rights internal service user. _checkAccessToResource() is emulated
 * through DOLIBARR_API_SOCID (empty = internal user, sees everything).
 */
#[Route('/api/thirdparties')]
final class SalesRepController extends AbstractController
{
    public function __construct(
        private readonly SalesRepService $reps,
        private readonly DolibarrContext $config,
    ) {
    }

    /**
     * Get a customer representative of a third party.
     *
     * Upstream: GET /thirdparties/{id}/representative
     */
    #[Route(
        '/{id}/representative',
        name: 'salesrep_get_representative',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function getRepresentative(int $id): JsonResponse
    {
        if (!$this->reps->thirdpartyExists($id)) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        $this->checkAccess($id);

        return new JsonResponse($this->reps->getSalesRepresentatives($id));
    }

    /**
     * Add a customer representative to a third party.
     *
     * Upstream: POST /thirdparties/{id}/representative/{representative_id}
     */
    #[Route(
        '/{id}/representative/{representative_id}',
        name: 'salesrep_add_representative',
        requirements: ['id' => '\d+', 'representative_id' => '\d+'],
        methods: ['POST'],
    )]
    public function addRepresentative(int $id, int $representative_id): JsonResponse
    {
        if (!$this->reps->thirdpartyExists($id)) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        if (!$this->reps->userExists($representative_id)) {
            throw new ApiErrorException(404, 'User not found');
        }
        $this->checkAccess($id);

        return new JsonResponse($this->reps->addCommercial($id, $representative_id));
    }

    /**
     * Remove the link between a customer representative and a third party.
     *
     * Upstream: DELETE /thirdparties/{id}/representative/{representative_id}
     */
    #[Route(
        '/{id}/representative/{representative_id}',
        name: 'salesrep_del_representative',
        requirements: ['id' => '\d+', 'representative_id' => '\d+'],
        methods: ['DELETE'],
    )]
    public function deleteRepresentative(int $id, int $representative_id): JsonResponse
    {
        if (!$this->reps->thirdpartyExists($id)) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        if (!$this->reps->userExists($representative_id)) {
            throw new ApiErrorException(404, 'User not found');
        }
        $this->checkAccess($id);

        return new JsonResponse($this->reps->delCommercial($id, $representative_id));
    }

    /**
     * Get representatives of a third party.
     *
     * Upstream: GET /thirdparties/{id}/representatives (?mode=0|1)
     */
    #[Route(
        '/{id}/representatives',
        name: 'salesrep_get_representatives',
        requirements: ['id' => '\d+'],
        methods: ['GET'],
    )]
    public function getSalesRepresentatives(int $id, Request $request): JsonResponse
    {
        if (empty($id)) {
            throw new ApiErrorException(400, 'Thirdparty ID is mandatory');
        }
        $this->checkAccess($id);
        if (!$this->reps->thirdpartyExists($id)) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $mode = (int) $request->query->get('mode', 0);

        return new JsonResponse($this->reps->getSalesRepresentatives($id, $mode));
    }

    /**
     * Port of DolibarrApi::_checkAccessToResource() — throws the same 403.
     */
    private function checkAccess(int $socid): void
    {
        if (!$this->config->checkAccessToThirdparty($socid)) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }
    }
}
