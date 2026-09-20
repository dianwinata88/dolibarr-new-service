<?php

declare(strict_types=1);

namespace App\Aux;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of the site-account endpoints of
 * htdocs/societe/class/api_thirdparties.class.php
 * (llx_societe_account CRUD attached to thirdparties).
 */
#[Route('/api/thirdparties')]
final class ThirdpartyAccountController extends AbstractController
{
    public function __construct(
        private readonly SocieteAccountService $accounts,
        private readonly DolibarrContext $config,
    ) {
    }

    /**
     * Get a specific account attached to a third party.
     *
     * Upstream: GET /thirdparties/{id}/accounts/ (?site=)
     */
    #[Route('/{id}/accounts', name: 'aux_get_societe_accounts', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[Route('/{id}/accounts/', name: 'aux_get_societe_accounts_slash', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getSocieteAccounts(int $id, Request $request): JsonResponse
    {
        $this->checkAccess($id);

        $site = $request->query->get('site');
        $rows = $this->accounts->findBySoc($id, is_string($site) && $site !== '' ? $site : null);
        if (count($rows) === 0) {
            throw new ApiErrorException(404, 'This thirdparty does not have any account attached or does not exist.');
        }

        // upstream refetches each account then keeps only these fields
        $fields = ['id', 'fk_soc', 'key_account', 'site', 'date_creation', 'tms'];
        $return = [];
        foreach ($rows as $row) {
            $account = $this->accounts->fetch((int) $row['rowid']);
            if ($account === null) {
                continue;
            }
            $return[] = array_intersect_key($account, array_flip($fields));
        }

        return new JsonResponse($return);
    }

    // Note: upstream GET /thirdparties/accounts/{site}/{key_account} is served
    // by App\ThirdParty\ThirdPartyController (it returns the cleaned Societe
    // object, which outranks this controller in route order).

    /**
     * Create and attach a new account to an existing third party.
     *
     * Upstream: POST /thirdparties/{id}/accounts
     */
    #[Route('/{id}/accounts', name: 'aux_create_societe_account', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createSocieteAccount(int $id, Request $request): JsonResponse
    {
        $this->checkAccess($id);

        $data = $this->body($request);
        if (!isset($data['site'])) {
            throw new ApiErrorException(
                422,
                'Unprocessable Entity: You must pass the site attribute in your request data !',
            );
        }

        if (count($this->accounts->findBySoc($id, (string) $data['site'])) === 0) {
            $account = $this->accounts->sanitizeInput($data);
            if (!isset($account['login'])) {
                $account['login'] = '';
            }
            $account['fk_soc'] = $id;

            $newid = $this->accounts->create($account);
            if ($newid < 0) {
                throw new ApiErrorException(
                    500,
                    'Error creating SocieteAccount entity. Ensure that the ID of thirdparty provided does exist!',
                );
            }

            return new JsonResponse($this->accounts->fetch($newid));
        }

        throw new ApiErrorException(409, 'A SocieteAccount entity already exists for this company and site.');
    }

    /**
     * Create and attach a new (or replace an existing) site account.
     *
     * Upstream: POST /thirdparties/{id}/accounts/{site}
     */
    #[Route(
        '/{id}/accounts/{site}',
        name: 'aux_post_societe_account',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function postSocieteAccount(int $id, string $site, Request $request): JsonResponse
    {
        $this->checkAccess($id);

        $data = $this->body($request);
        $existing = $this->accounts->fetchBySocAndSite($id, $site);

        if ($existing === null) {
            if (!isset($data['key_account'])) {
                throw new ApiErrorException(
                    422,
                    'Unprocessable Entity: You must pass the key_account attribute in your request data !',
                );
            }
            $account = $this->accounts->sanitizeInput($data);
            if (!isset($account['login'])) {
                $account['login'] = '';
            }
            // On create, fk_soc and site from the body are ignored — the URL wins
            $account['fk_soc'] = $id;
            $account['site'] = $site;

            $newid = $this->accounts->create($account);
            if ($newid < 0) {
                throw new ApiErrorException(500, 'Error creating SocieteAccount entity.');
            }
            $result = $this->accounts->fetch($newid);
        } else {
            if (isset($data['site']) && $data['site'] !== $site) {
                $collision = $this->accounts->findBySoc($id, (string) $data['site']);
                if (count($collision) !== 0) {
                    throw new ApiErrorException(
                        409,
                        'You are trying to update this thirdparty Account for ' . $site . ' to ' . $data['site']
                            . ' but another Account already exists with this site key.',
                    );
                }
                // Upstream quirk: the collision result replaces the account
                // result set, so the row below reads the (empty) new-site
                // query and the update then targets rowid 0 — a no-op that
                // still returns 200. Kept verbatim.
                $existing = null;
            }

            $account = $this->accounts->sanitizeInput($data);
            if (!isset($account['login'])) {
                $account['login'] = '';
            }
            $account['fk_soc'] = $id;
            $account['site'] = $site;
            if ($existing !== null) {
                $account['fk_user_creat'] = (int) $existing['fk_user_creat'];
                $account['date_creation'] = $existing['date_creation'];
            }

            if ($this->accounts->update($existing !== null ? (int) $existing['rowid'] : 0, $account) < 0) {
                throw new ApiErrorException(500, 'Error updating SocieteAccount entity.');
            }
            $result = $existing !== null ? $this->accounts->fetch((int) $existing['rowid']) : $account + ['id' => null];
        }

        return new JsonResponse($result);
    }

    /**
     * Update specified values of a specific account.
     *
     * Upstream: PUT /thirdparties/{id}/accounts/{site}
     */
    #[Route('/{id}/accounts/{site}', name: 'aux_put_societe_account', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function putSocieteAccount(int $id, string $site, Request $request): JsonResponse
    {
        $this->checkAccess($id);

        $data = $this->body($request);
        $existing = $this->accounts->fetchBySocAndSite($id, $site);

        if ($existing === null) {
            throw new ApiErrorException(
                404,
                'This thirdparty does not have ' . $site . ' account attached or does not exist.',
            );
        }

        if (isset($data['site']) && $data['site'] !== $site) {
            $collision = $this->accounts->findBySoc($id, (string) $data['site']);
            if (count($collision) !== 0) {
                throw new ApiErrorException(
                    409,
                    'You are trying to update this thirdparty Account for ' . $site . ' to ' . $data['site']
                        . ' but another Account already exists for this thirdparty with this site key.',
                );
            }
            // Same upstream quirk as postSocieteAccount: the rename check
            // consumes the row pointer, so the update targets a null rowid.
            $existing = null;
        }

        $account = $existing ?? [];
        foreach ($this->accounts->sanitizeInput($data) as $field => $value) {
            $account[$field] = $value;
        }
        unset($account['id'], $account['rowid']);

        if ($this->accounts->update($existing !== null ? (int) $existing['rowid'] : 0, $account) < 0) {
            throw new ApiErrorException(500, 'Error updating SocieteAccount account');
        }

        return new JsonResponse($existing !== null ? $this->accounts->fetch((int) $existing['rowid']) : $account);
    }

    /**
     * Delete a specific site account attached to a third party.
     *
     * Upstream: DELETE /thirdparties/{id}/accounts/{site}
     */
    #[Route(
        '/{id}/accounts/{site}',
        name: 'aux_delete_societe_account',
        requirements: ['id' => '\d+'],
        methods: ['DELETE'],
    )]
    public function deleteSocieteAccount(int $id, string $site): Response
    {
        $this->checkAccess($id);

        $existing = $this->accounts->fetchBySocAndSite($id, $site);
        if ($existing === null) {
            throw new ApiErrorException(404);
        }

        if ($this->accounts->delete((int) $existing['rowid']) < 0) {
            throw new ApiErrorException(500, 'Error while deleting ' . $site . ' account attached to this third party');
        }

        return new Response('', 200);
    }

    /**
     * Delete all accounts attached to a third party.
     *
     * Upstream: DELETE /thirdparties/{id}/accounts
     */
    #[Route('/{id}/accounts', name: 'aux_delete_societe_accounts', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteSocieteAccounts(int $id): Response
    {
        $this->checkAccess($id);

        $rows = $this->accounts->findBySoc($id);
        if (count($rows) === 0) {
            throw new ApiErrorException(404, 'This third party does not have any account attached or does not exist.');
        }

        foreach ($rows as $row) {
            if ($this->accounts->delete((int) $row['rowid']) < 0) {
                throw new ApiErrorException(500, 'Error while deleting account attached to this third party');
            }
        }

        return new Response('', 200);
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

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return is_array($data) ? $data : [];
    }
}
