<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of htdocs/societe/class/api_thirdparties.class.php.
 *
 * Dolibarr dispatches these endpoints under /api/index.php/thirdparties;
 * here they live under /api/thirdparties. Response bodies and error
 * statuses/messages are replicated verbatim.
 *
 * Upstream permission checks (hasRight('societe', 'lire'|'creer'|'supprimer'))
 * are satisfied by the API-key firewall: every authenticated request maps to
 * a full-rights internal service user (DOLIBARR_API_USER_*). The
 * _checkAccessToResource() socid filter is emulated through
 * DOLIBARR_API_SOCID (empty = internal user, sees everything).
 *
 * Endpoints that reference non-CRM objects (orders, invoices, proposals,
 * fiches inter) keep the upstream response SHAPE but operate on soft
 * references only — the joined data (e.g. invoice ref) comes back null.
 */
#[Route('/api/thirdparties')]
final class ThirdPartyController extends AbstractController
{
    /** Mandatory fields for POST (Thirdparties::$FIELDS + SOCIETE_EMAIL_MANDATORY). */
    private array $mandatoryFields;

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
        private readonly ThirdpartyService $thirdpartyService,
        private readonly CategoryService $categoryService,
        private readonly DiscountService $discountService,
        private readonly NotificationService $notificationService,
        private readonly BankAccountService $bankAccountService,
        private readonly SocieteAccountService $societeAccountService,
        private readonly ThirdpartySerializer $serializer,
    ) {
        $this->mandatoryFields = ['name'];
        if ($this->config->getString('SOCIETE_EMAIL_MANDATORY')) {
            $this->mandatoryFields[] = 'email';
        }
    }

    // ==================================================================
    //  GET /api/thirdparties — index()
    // ==================================================================

    #[Route('', name: 'thirdparties_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $sortfield = (string) ($request->query->get('sortfield') ?? 't.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 100);
        $page = (int) ($request->query->get('page') ?? 0);
        $mode = (int) ($request->query->get('mode') ?? 0);
        $category = (int) ($request->query->get('category') ?? 0);
        $sqlfilters = (string) ($request->query->get('sqlfilters') ?? '');
        $properties = (string) ($request->query->get('properties') ?? '');
        $paginationData = $request->query->getBoolean('pagination_data', false);

        // case of external user, we force socids
        $socids = (string) $this->config->getString('DOLIBARR_API_SOCID');
        $searchSale = 0;
        if (!$this->config->getBool('DOLIBARR_API_RIGHT_SOCIETE_CLIENT_VOIR', true) && $socids === '') {
            $searchSale = $this->config->apiUserId();
        }

        $sql = 'SELECT t.rowid';
        $sql .= ' FROM llx_societe as t';
        $sql .= ' LEFT JOIN llx_societe_extrafields AS ef ON ef.fk_object = t.rowid';
        if ($category > 0) {
            if ($mode !== 4) {
                $sql .= ', llx_categorie_societe as c';
            }
            if (!in_array($mode, [1, 2, 3], true)) {
                $sql .= ', llx_categorie_fournisseur as cc';
            }
        }
        $sql .= ' WHERE t.entity IN ('.$this->config->getEntity('societe').')';
        if ($mode === 1) {
            $sql .= ' AND t.client IN (1, 3)';
        } elseif ($mode === 2) {
            $sql .= ' AND t.client IN (2, 3)';
        } elseif ($mode === 3) {
            $sql .= ' AND t.client IN (0)';
        } elseif ($mode === 4) {
            $sql .= ' AND t.fournisseur IN (1)';
        }
        if ($category > 0) {
            if ($mode !== 0 && $mode !== 4) {
                $sql .= ' AND c.fk_categorie = '.$category.' AND c.fk_soc = t.rowid';
            } elseif ($mode === 4) {
                $sql .= ' AND cc.fk_categorie = '.$category.' AND cc.fk_soc = t.rowid';
            } else {
                $sql .= ' AND ((c.fk_categorie = '.$category.' AND c.fk_soc = t.rowid) OR (cc.fk_categorie = '.$category.' AND cc.fk_soc = t.rowid))';
            }
        }
        if ($socids !== '') {
            $sql .= ' AND t.rowid IN ('.implode(',', array_map('intval', explode(',', $socids))).')';
        }
        if ($searchSale && $searchSale != -1) {
            if ($searchSale == -2) {
                $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE sc.fk_soc = t.rowid AND sc.fk_user IS NULL)';
            } elseif ($searchSale > 0) {
                $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE sc.fk_soc = t.rowid AND sc.fk_user = '.(int) $searchSale.')';
            }
        }
        if ($sqlfilters !== '') {
            $errormessage = '';
            $sql .= (new UniversalSearchFilter($this->db))->forge($sqlfilters, $errormessage);
            if ($errormessage !== '') {
                throw new ApiErrorException(400, 'Error when validating parameter sqlfilters -> '.$errormessage);
            }
        }

        $sqlTotals = str_replace('SELECT t.rowid', 'SELECT count(t.rowid) as total', $sql);

        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $sql .= ' LIMIT '.($limit + 1).' OFFSET '.($limit * $page);
        }

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            throw new ApiErrorException(503, 'Error when retrieve third parties : '.$e->getMessage());
        }

        $objRet = [];
        $num = count($rows);
        $min = min($num, ($limit <= 0 ? $num : $limit));
        for ($i = 0; $i < $min; $i++) {
            $c = new Company();
            if ($this->thirdpartyService->fetch($c, (int) $rows[$i]['rowid']) > 0) {
                $data = $this->serializer->toArray($c);
                if ($properties !== '') {
                    $data = $this->serializer->filterProperties($data, $properties);
                }
                $objRet[] = $data;
            }
        }

        if (!count($objRet)) {
            $message = match ($mode) {
                1 => 'No customers found',
                2 => 'No prospects found',
                3 => 'No other third parties found',
                4 => 'No suppliers found',
                default => 'No third parties found',
            };
            throw new ApiErrorException(404, $message);
        }

        if ($paginationData) {
            $total = (int) ($this->db->fetchOne($sqlTotals) ?: 0);
            $objRet = [
                'data' => $objRet,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'page_count' => (int) ceil($total / $limit),
                    'limit' => $limit,
                ],
            ];
        }

        return new JsonResponse($objRet);
    }

    // ==================================================================
    //  GET — _fetch()
    // ==================================================================

    /** Load a Company by rowid, or null (fetch() > 0 check like upstream). */
    private function load(int $id): ?Company
    {
        $c = new Company();
        if ($this->thirdpartyService->fetch($c, $id) <= 0) {
            return null;
        }

        return $c;
    }

    /** @return JsonResponse */
    private function doFetch(int $rowid, string $ref = '', string $refExt = '', string $barcode = '', string $email = ''): JsonResponse
    {
        $c = new Company();
        if ($this->thirdpartyService->fetch($c, $rowid, $ref, $refExt, $barcode, '', '', '', '', '', '', $email) <= 0) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $filterabsolute = $this->config->getString('FACTURE_DEPOSITS_ARE_JUST_PAYMENTS')
            ? 'fk_facture_source IS NULL'
            : "fk_facture_source IS NULL OR (description LIKE '(DEPOSIT)%' AND description NOT LIKE '(EXCESS RECEIVED)%')";
        $filtercreditnote = $this->config->getString('FACTURE_DEPOSITS_ARE_JUST_PAYMENTS')
            ? 'fk_facture_source IS NOT NULL'
            : "fk_facture_source IS NOT NULL AND (description NOT LIKE '(DEPOSIT)%' OR description LIKE '(EXCESS RECEIVED)%')";

        $c->absolute_discount = (float) $this->utils->price2num(
            $this->discountService->getAvailableDiscounts($c, $filterabsolute), 'MT');
        $c->absolute_creditnote = (float) $this->utils->price2num(
            $this->discountService->getAvailableDiscounts($c, $filtercreditnote), 'MT');

        return new JsonResponse($this->serializer->toArray($c));
    }

    #[Route('/{id}', name: 'thirdparties_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        return $this->doFetch($id);
    }

    #[Route('/email/{email}', name: 'thirdparties_get_by_email', methods: ['GET'])]
    public function getByEmail(string $email): JsonResponse
    {
        return $this->doFetch(0, '', '', '', $email);
    }

    #[Route('/barcode/{barcode}', name: 'thirdparties_get_by_barcode', methods: ['GET'])]
    public function getByBarcode(string $barcode): JsonResponse
    {
        return $this->doFetch(0, '', '', $barcode);
    }

    // ==================================================================
    //  POST /api/thirdparties — post()
    // ==================================================================

    #[Route('', name: 'thirdparties_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $requestData = $this->body($request);

        // External api user does not know internal country ID
        if (!isset($requestData['country_id']) && isset($requestData['country_code'])) {
            $field = strlen((string) $requestData['country_code']) > 2 ? 'code_iso' : 'code';
            $countryId = $this->utils->getIdFromCode((string) $requestData['country_code'], 'c_country', $field, 'rowid', true);
            if ($countryId === -1) {
                throw new ApiErrorException(404, 'Country code not found in database: DB_ERROR');
            }
            $requestData['country_id'] = $countryId !== '' ? (int) $countryId : null;
        }

        foreach ($this->mandatoryFields as $field) {
            if (!isset($requestData[$field])) {
                throw new ApiErrorException(400, "$field field missing");
            }
        }

        $c = new Company();
        foreach ($requestData as $field => $value) {
            if ($field === 'caller') {
                $c->context = $c->context ?: [];
                $c->context['caller'] = $this->utils->sanitizeVal($requestData['caller'], 'aZ09');
                continue;
            }
            if ($field === 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $c->array_options[$index] = $val;
                }
                continue;
            }
            $c->$field = $value;
        }

        $id = $this->thirdpartyService->create($c);
        if ($id < 0) {
            $svc = $this->thirdpartyService;
            throw new ApiErrorException(500, 'Error creating thirdparty', array_merge([$svc->error], $svc->errors));
        }

        return new JsonResponse($id);
    }

    // ==================================================================
    //  PUT /api/thirdparties/{id} — put()
    // ==================================================================

    #[Route('/{id}', name: 'thirdparties_put', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $requestData = $this->body($request);
        foreach ($requestData as $field => $value) {
            if ($field === 'id') {
                continue;
            }
            if ($field === 'caller') {
                $c->context = $c->context ?: [];
                $c->context['caller'] = $this->utils->sanitizeVal($requestData['caller'], 'aZ09');
                continue;
            }
            if ($field === 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $c->array_options[$index] = $val;
                }
                continue;
            }
            $c->$field = $value;
        }

        if ($this->thirdpartyService->update($c, $id, 1, 1, 'update') > 0) {
            return $this->get($id);
        }

        throw new ApiErrorException(500, $this->thirdpartyService->error ?? 'Error when update thirdparty');
    }

    // ==================================================================
    //  PUT /{id}/merge/{idtodelete} — merge()
    // ==================================================================

    #[Route('/{id}/merge/{idtodelete}', name: 'thirdparties_merge', requirements: ['id' => '\d+', 'idtodelete' => '\d+'], methods: ['PUT'])]
    public function merge(int $id, int $idtodelete): JsonResponse
    {
        if ($id === $idtodelete) {
            throw new ApiErrorException(400, 'Try to merge a thirdparty into itself');
        }
        $target = $this->load($id);
        if ($target === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        if ($this->load($idtodelete) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $result = $this->thirdpartyService->mergeCompany($target, $idtodelete, $this->categoryService);
        if ($result < 0) {
            throw new ApiErrorException(500, 'Error failed to merged thirdparty '.$idtodelete.' into '.$id.'. Enable and read log file for more information.');
        }

        return $this->get($id);
    }

    // ==================================================================
    //  DELETE /{id} — delete()
    // ==================================================================

    #[Route('/{id}', name: 'thirdparties_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        $c->oldcopy = clone $c;

        $res = $this->thirdpartyService->delete($c, $id);
        if ($res < 0) {
            throw new ApiErrorException(500, "Can't delete, error occurs");
        }
        if ($res === 0) {
            throw new ApiErrorException(409, "Can't delete, that product is probably used");
        }

        return new JsonResponse(['success' => ['code' => 200, 'message' => 'Object deleted']]);
    }

    // ==================================================================
    //  PUT /{id}/setpricelevel/{priceLevel}
    // ==================================================================

    #[Route('/{id}/setpricelevel/{priceLevel}', name: 'thirdparties_setpricelevel', requirements: ['id' => '\d+', 'priceLevel' => '\d+'], methods: ['PUT'])]
    public function setThirdpartyPriceLevel(int $id, int $priceLevel): JsonResponse
    {
        if (!$this->config->isModEnabled('societe')) {
            throw new ApiErrorException(501, 'Module "Thirdparties" needed for this request');
        }
        if (!$this->config->isModEnabled('product')) {
            throw new ApiErrorException(501, 'Module "Products" needed for this request');
        }
        if (!$this->config->getString('PRODUIT_MULTIPRICES') && !$this->config->getString('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')) {
            throw new ApiErrorException(501, 'Multiprices features activation needed for this request');
        }
        $limit = (int) $this->config->getInt('PRODUIT_MULTIPRICES_LIMIT');
        if ($priceLevel < 1 || ($limit > 0 && $priceLevel > $limit)) {
            throw new ApiErrorException(400, 'Price level must be between 1 and '.$this->config->getString('PRODUIT_MULTIPRICES_LIMIT'));
        }

        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Thirdparty '.$id.' not found');
        }

        $result = $this->thirdpartyService->setPriceLevel($c, $priceLevel);
        if ($result <= 0) {
            throw new ApiErrorException(500, 'Error setting new price level for thirdparty '.$id, [$this->thirdpartyService->error]);
        }

        return new JsonResponse($this->serializer->toArray($c));
    }

    // ==================================================================
    //  representatives
    // ==================================================================

    #[Route('/{id}/representative', name: 'thirdparties_get_representative', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getRepresentative(int $id): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        return new JsonResponse($this->thirdpartyService->getSalesRepresentatives($id));
    }

    #[Route('/{id}/representative/{representative_id}', name: 'thirdparties_add_representative', requirements: ['id' => '\d+', 'representative_id' => '\d+'], methods: ['POST'])]
    public function addRepresentative(int $id, int $representative_id): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        if (!$this->userExists($representative_id)) {
            throw new ApiErrorException(404, 'User not found');
        }

        return new JsonResponse($this->thirdpartyService->addCommercial($c, $representative_id));
    }

    #[Route('/{id}/representative/{representative_id}', name: 'thirdparties_del_representative', requirements: ['id' => '\d+', 'representative_id' => '\d+'], methods: ['DELETE'])]
    public function deleteRepresentative(int $id, int $representative_id): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        if (!$this->userExists($representative_id)) {
            throw new ApiErrorException(404, 'User not found');
        }

        return new JsonResponse($this->thirdpartyService->delCommercial($c, $representative_id));
    }

    #[Route('/{id}/representatives', name: 'thirdparties_representatives', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getSalesRepresentatives(int $id, Request $request): JsonResponse
    {
        $mode = (int) ($request->query->get('mode') ?? 0);
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        return new JsonResponse($this->thirdpartyService->getSalesRepresentatives($id, $mode));
    }

    // ==================================================================
    //  categories
    // ==================================================================

    #[Route('/{id}/categories', name: 'thirdparties_get_categories', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getCategories(int $id, Request $request): JsonResponse
    {
        return $this->categories($id, 'customer', $request);
    }

    #[Route('/{id}/categories/{category_id}', name: 'thirdparties_add_category', requirements: ['id' => '\d+', 'category_id' => '\d+'], methods: ['PUT'])]
    public function addCategory(int $id, int $category_id): JsonResponse
    {
        return $this->linkCategory($id, $category_id, 'customer', 'add');
    }

    #[Route('/{id}/categories/{category_id}', name: 'thirdparties_del_category', requirements: ['id' => '\d+', 'category_id' => '\d+'], methods: ['DELETE'])]
    public function deleteCategory(int $id, int $category_id): JsonResponse
    {
        return $this->linkCategory($id, $category_id, 'customer', 'del');
    }

    #[Route('/{id}/supplier_categories', name: 'thirdparties_get_supplier_categories', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getSupplierCategories(int $id, Request $request): JsonResponse
    {
        return $this->categories($id, 'supplier', $request);
    }

    #[Route('/{id}/supplier_categories/{category_id}', name: 'thirdparties_add_supplier_category', requirements: ['id' => '\d+', 'category_id' => '\d+'], methods: ['PUT'])]
    public function addSupplierCategory(int $id, int $category_id): JsonResponse
    {
        return $this->linkCategory($id, $category_id, 'supplier', 'add');
    }

    #[Route('/{id}/supplier_categories/{category_id}', name: 'thirdparties_del_supplier_category', requirements: ['id' => '\d+', 'category_id' => '\d+'], methods: ['DELETE'])]
    public function deleteSupplierCategory(int $id, int $category_id): JsonResponse
    {
        return $this->linkCategory($id, $category_id, 'supplier', 'del');
    }

    private function categories(int $id, string $type, Request $request): JsonResponse
    {
        $sortfield = (string) ($request->query->get('sortfield') ?? 's.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 0);
        $page = (int) ($request->query->get('page') ?? 0);

        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $cats = $this->categoryService->getListForItem($id, $type, $sortfield, $sortorder, $limit, $page);
        if ($cats === -1) {
            throw new ApiErrorException(503, 'Error when retrieve categories : '.$this->categoryService->getLastError());
        }

        return new JsonResponse($cats);
    }

    private function linkCategory(int $id, int $categoryId, string $type, string $op): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        if ($this->categoryService->fetch($categoryId) === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        if ($op === 'add') {
            $this->categoryService->addType($categoryId, $id, $type);
        } else {
            $this->categoryService->delType($categoryId, $id, $type);
        }

        return new JsonResponse($this->serializer->toArray($c));
    }

    // ==================================================================
    //  outstanding (non-CRM soft refs — shape kept, refs null)
    // ==================================================================

    #[Route('/{id}/outstandingproposals', name: 'thirdparties_outstanding_proposals', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getOutStandingProposals(int $id): JsonResponse
    {
        return $this->outstanding($id, 'propal');
    }

    #[Route('/{id}/outstandingorders', name: 'thirdparties_outstanding_orders', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getOutStandingOrder(int $id): JsonResponse
    {
        return $this->outstanding($id, 'commande');
    }

    #[Route('/{id}/outstandinginvoices', name: 'thirdparties_outstanding_invoices', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getOutStandingInvoices(int $id): JsonResponse
    {
        return $this->outstanding($id, 'facture');
    }

    /**
     * Port of getOutstandingProposals/Orders/Bills. The source tables
     * (propal/commande/facture + supplier variants) belong to other domains
     * and do not exist in this service, so the query returns the empty
     * shape upstream produces when no rows match.
     */
    private function outstanding(int $id, string $kind): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $mode = 'customer';

        $opened = 0.0;
        $totalHt = 0.0;
        $totalTtc = 0.0;
        $refs = [];
        $refsOpened = [];

        $table = match ([$kind, $mode]) {
            ['propal', 'supplier'] => 'supplier_proposal',
            ['propal', 'customer'] => 'propal',
            ['commande', 'supplier'] => 'commande_fournisseur',
            ['commande', 'customer'] => 'commande',
            ['facture', 'supplier'] => 'facture_fourn',
            default => 'facture',
        };

        if ($this->thirdpartyService->tableExists('llx_'.$table)) {
            $sql = 'SELECT rowid, ref, total_ht, total_ttc, fk_statut as status FROM llx_'.$table.' as f WHERE fk_soc = '.(int) $id;
            foreach ($this->db->fetchAllAssociative($sql) as $obj) {
                $refs[$obj['rowid']] = $obj['ref'];
                $totalHt += (float) $obj['total_ht'];
                $totalTtc += (float) $obj['total_ttc'];
                if ((int) $obj['status'] !== 0) {
                    $opened += (float) $obj['total_ttc'];
                    $refsOpened[$obj['rowid']] = $obj['ref'];
                }
            }
        }

        // API unsets total_ht / total_ttc before returning
        return new JsonResponse(['opened' => $opened, 'refs' => (object) $refs, 'refsopened' => (object) $refsOpened]);
    }

    // ==================================================================
    //  fixedamountdiscounts
    // ==================================================================

    #[Route('/{id}/fixedamountdiscounts', name: 'thirdparties_fixedamountdiscounts', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getFixedAmountDiscounts(int $id, Request $request): JsonResponse
    {
        $mode = (string) ($request->query->get('mode') ?? 'customer');
        $filter = (string) ($request->query->get('filter') ?? 'none');
        $sortfield = (string) ($request->query->get('sortfield') ?? 'f.type');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');

        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $objRet = [];
        if ($mode === 'customer') {
            // upstream LEFT JOIN llx_facture f — non-CRM table: ref/factype emitted null
            $sql = "SELECT null as ref, null as factype, re.fk_facture_source, re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc, re.description, re.fk_facture, re.fk_facture_line"
                ." FROM llx_societe_remise_except as re"
                ." WHERE re.fk_soc = ".(int) $id;
            if ($filter === 'available') {
                $sql .= ' AND re.fk_facture IS NULL AND re.fk_facture_line IS NULL';
            }
            if ($filter === 'used') {
                $sql .= ' AND (re.fk_facture IS NOT NULL OR re.fk_facture_line IS NOT NULL)';
            }
        } elseif ($mode === 'supplier') {
            $sql = "SELECT null as ref, null as factype, re.fk_invoice_supplier_source, re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc, re.description, re.fk_invoice_supplier, re.fk_invoice_supplier_line"
                ." FROM llx_societe_remise_except as re"
                ." WHERE re.fk_invoice_supplier_source IS NOT NULL AND re.fk_soc = ".(int) $id;
            if ($filter === 'available') {
                $sql .= ' AND re.fk_invoice_supplier IS NULL AND re.fk_invoice_supplier_line IS NULL';
            }
            if ($filter === 'used') {
                $sql .= ' AND (re.fk_invoice_supplier IS NOT NULL OR re.fk_invoice_supplier_line IS NOT NULL)';
            }
        } else {
            return new JsonResponse($objRet);
        }

        // llx_facture is not joined (non-CRM): map its soft-ref sort keys
        // onto the emitted constant aliases so ordering remains deterministic.
        $sortfield = str_replace(['f.type', 'f.ref'], ['factype', 'ref'], $sortfield);
        try {
            $rows = $this->db->fetchAllAssociative($sql.$this->orderBy($sortfield, $sortorder));
        } catch (\Throwable $e) {
            throw new ApiErrorException(503, $e->getMessage());
        }

        return new JsonResponse($rows);
    }

    #[Route('/{id}/fixedamountdiscounts', name: 'thirdparties_create_fixedamountdiscounts', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createFixedAmountDiscount(int $id, Request $request): JsonResponse
    {
        $requestData = $this->body($request);

        if (!isset($requestData['amount'])) {
            throw new ApiErrorException(400, 'Missing required field: amount');
        }
        if (!isset($requestData['description'])) {
            throw new ApiErrorException(400, 'Missing required field: description');
        }

        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Error creating discount, thirdparty not found');
        }

        if (!is_numeric($requestData['amount']) || $requestData['amount'] <= 0) {
            throw new ApiErrorException(400, 'Invalid amount_ht: must be a positive number');
        }
        $amount = (float) $requestData['amount'];

        if (isset($requestData['tva_tx']) && (!is_numeric($requestData['tva_tx']) || $requestData['tva_tx'] < 0)) {
            throw new ApiErrorException(400, 'Invalid tva_tx: must be a positive number or zero');
        }
        $tvaTx = isset($requestData['tva_tx']) ? (float) $requestData['tva_tx'] : 0.0;

        $priceBaseType = 'HT';
        if (isset($requestData['price_base_type'])) {
            $priceBaseType = strtoupper((string) $requestData['price_base_type']);
            if ($priceBaseType !== 'HT' && $priceBaseType !== 'TTC') {
                throw new ApiErrorException(400, 'Invalid price_base_type: must be "HT" or "TTC"');
            }
        }

        $discountType = 0;
        if (isset($requestData['discount_type'])) {
            $discountType = (int) $requestData['discount_type'];
            if ($discountType !== 0 && $discountType !== 1) {
                throw new ApiErrorException(400, 'Invalid discount_type: must be 0 (customer) or 1 (supplier)');
            }
        }

        $description = (string) $requestData['description'];
        if (trim($description) === '') {
            throw new ApiErrorException(400, 'Description cannot be empty');
        }

        $vatrate = '';
        if (isset($requestData['vat_src_code']) && !empty($requestData['vat_src_code'])) {
            $vatrate = $tvaTx.' ('.$requestData['vat_src_code'].')';
        } else {
            $vatrate = (string) $tvaTx;
        }

        $this->db->beginTransaction();
        try {
            $result = $this->discountService->setRemiseExcept($c, $amount, $description, $vatrate, $discountType, $priceBaseType);
            if ($result > 0) {
                $this->db->commit();

                return new JsonResponse($result);
            }
            $this->db->rollBack();
            $svc = $this->discountService;
            throw new ApiErrorException(500, 'Error creating discount: '.$svc->error, array_merge([$svc->error], $svc->errors));
        } catch (ApiErrorException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new ApiErrorException(500, 'Error creating discount: '.$e->getMessage());
        }
    }

    // ==================================================================
    //  POST /{id}/splitdiscount/{discountid}
    // ==================================================================

    #[Route('/{id}/splitdiscount/{discountid}', name: 'thirdparties_splitdiscount', requirements: ['id' => '\d+', 'discountid' => '\d+'], methods: ['POST'])]
    public function splitDiscount(int $id, int $discountid, Request $request): JsonResponse
    {
        $amountTtc1 = (float) $request->request->get('amount_ttc_1', $request->query->get('amount_ttc_1', 0));
        $amountTtc2 = (float) $request->request->get('amount_ttc_2', $request->query->get('amount_ttc_2', 0));
        // amounts may also arrive inside a JSON body
        $body = $this->body($request);
        if (isset($body['amount_ttc_1'])) {
            $amountTtc1 = (float) $body['amount_ttc_1'];
        }
        if (isset($body['amount_ttc_2'])) {
            $amountTtc2 = (float) $body['amount_ttc_2'];
        }

        if (empty($discountid)) {
            throw new ApiErrorException(400, 'Discount ID is mandatory');
        }
        if (empty($amountTtc1) || empty($amountTtc2)) {
            throw new ApiErrorException(400, 'Amount are mandatory');
        }

        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        $discount = $this->discountService->fetch($discountid);
        if ($discount === null) {
            throw new ApiErrorException(404, 'Discount not found');
        }
        if ((int) $discount['fk_soc'] !== $id) {
            throw new ApiErrorException(405, 'Discount not owned by this thirdpartie');
        }
        if ((float) $this->utils->price2num($amountTtc1 + $amountTtc2) != (float) $discount['amount_ttc']) {
            throw new ApiErrorException(405, 'Sum of the 2 discounts is different that the original discount');
        }
        if (!empty($discount['fk_facture_line'])) {
            throw new ApiErrorException(409, 'Discount is already used');
        }

        $mk = function (float $amountTtc) use ($discount) {
            $d = new Discount();
            $d->fk_facture_source = $discount['fk_facture_source'] !== null ? (int) $discount['fk_facture_source'] : null;
            $d->fk_facture = $discount['fk_facture'] !== null ? (int) $discount['fk_facture'] : null;
            $d->fk_facture_line = $discount['fk_facture_line'] !== null ? (int) $discount['fk_facture_line'] : null;
            $d->fk_invoice_supplier_source = $discount['fk_invoice_supplier_source'] !== null ? (int) $discount['fk_invoice_supplier_source'] : null;
            $d->fk_invoice_supplier = $discount['fk_invoice_supplier'] !== null ? (int) $discount['fk_invoice_supplier'] : null;
            $d->fk_invoice_supplier_line = $discount['fk_invoice_supplier_line'] !== null ? (int) $discount['fk_invoice_supplier_line'] : null;
            $d->fk_soc = (int) $discount['fk_soc'];
            $d->socid = (int) $discount['fk_soc'];
            $d->discount_type = (int) $discount['discount_type'];
            $d->datec = strtotime((string) $discount['datec']) ?: time();
            $d->tva_tx = (float) $discount['tva_tx'];
            $d->vat_src_code = $discount['vat_src_code'];
            $d->multicurrency_code = $discount['multicurrency_code'];
            $d->multicurrency_tx = (float) ($discount['multicurrency_tx'] ?? 1);

            return $d;
        };

        $d1 = $mk($amountTtc1);
        $d2 = $mk($amountTtc2);

        $desc = (string) $discount['description'];
        if ($desc === '(CREDIT_NOTE)' || $desc === '(DEPOSIT)') {
            $d1->description = $desc;
            $d2->description = $desc;
        } else {
            $d1->description = $desc.' (1)';
            $d2->description = $desc.' (2)';
        }

        $d1->amount_ttc = $amountTtc1;
        $d2->amount_ttc = (float) $this->utils->price2num((float) $discount['amount_ttc'] - $d1->amount_ttc);
        $d1->amount_ht = (float) $this->utils->price2num($d1->amount_ttc / (1 + $d1->tva_tx / 100), 'MT');
        $d2->amount_ht = (float) $this->utils->price2num($d2->amount_ttc / (1 + $d2->tva_tx / 100), 'MT');
        $d1->amount_tva = (float) $this->utils->price2num($d1->amount_ttc - $d1->amount_ht);
        $d2->amount_tva = (float) $this->utils->price2num($d2->amount_ttc - $d2->amount_ht);

        $origMc = (float) ($discount['multicurrency_amount_ttc'] ?? 0);
        $origTtc = (float) $discount['amount_ttc'];
        $d1->multicurrency_amount_ttc = $amountTtc1 * ($origTtc != 0 ? $origMc / $origTtc : 0);
        $d2->multicurrency_amount_ttc = (float) $this->utils->price2num($origMc - $d1->multicurrency_amount_ttc);
        $d1->multicurrency_amount_ht = (float) $this->utils->price2num($d1->multicurrency_amount_ttc / (1 + $d1->tva_tx / 100), 'MT');
        $d2->multicurrency_amount_ht = (float) $this->utils->price2num($d2->multicurrency_amount_ttc / (1 + $d2->tva_tx / 100), 'MT');
        $d1->multicurrency_amount_tva = (float) $this->utils->price2num($d1->multicurrency_amount_ttc - $d1->multicurrency_amount_ht);
        $d2->multicurrency_amount_tva = (float) $this->utils->price2num($d2->multicurrency_amount_ttc - $d2->multicurrency_amount_ht);

        $this->db->beginTransaction();
        try {
            $res = $this->discountService->delete($discountid);
            $newid1 = $this->discountService->create($d1);
            $newid2 = $this->discountService->create($d2);
            if ($res <= 0 || $newid1 <= 0 || $newid2 <= 0) {
                $this->db->rollBack();
                throw new ApiErrorException(500, 'Operation fail');
            }
            $this->db->commit();
        } catch (ApiErrorException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            throw new ApiErrorException(500, 'Operation fail');
        }

        // upstream joins llx_facture for ref/factype — non-CRM soft ref: null
        $objRet = $this->db->fetchAllAssociative(
            'SELECT null as ref, null as factype, re.fk_facture_source, re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc, re.description, re.fk_facture, re.fk_facture_line'
            .' FROM llx_societe_remise_except as re'
            .' WHERE re.rowid IN ('.$newid1.','.$newid2.') AND re.fk_soc = '.(int) $id
            .$this->orderBy('factype', 'ASC'),
        );

        return new JsonResponse($objRet);
    }

    // ==================================================================
    //  invoices qualified — non-CRM: empty result sets
    // ==================================================================

    #[Route('/{id}/getinvoicesqualifiedforreplacement', name: 'thirdparties_invoices_qualified_replacement', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getInvoicesQualifiedForReplacement(int $id): JsonResponse
    {
        // list_replacable_invoices() queries llx_facture which is owned by
        // the (future) invoicing domain: no such table here → upstream
        // returns array() when no invoice qualifies.
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        return new JsonResponse([]);
    }

    #[Route('/{id}/getinvoicesqualifiedforcreditnote', name: 'thirdparties_invoices_qualified_creditnote', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getInvoicesQualifiedForCreditNote(int $id): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        return new JsonResponse([]);
    }

    // ==================================================================
    //  notifications
    // ==================================================================

    #[Route('/{id}/notifications', name: 'thirdparties_get_notifications', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getCompanyNotification(int $id): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        $rows = $this->notificationService->listForCompany($id);
        if (count($rows) === 0) {
            throw new ApiErrorException(404, 'Notification not found');
        }

        $fields = ['id', 'socid', 'event', 'contact_id', 'datec', 'tms', 'type'];
        $ret = [];
        foreach ($rows as $row) {
            $ret[] = array_intersect_key($row, array_flip($fields));
        }

        return new JsonResponse($ret);
    }

    #[Route('/{id}/notifications', name: 'thirdparties_create_notification', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createCompanyNotification(int $id, Request $request): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Error creating Thirdparty Notification, Thirdparty doesn\'t exists');
        }
        $data = $this->body($request);
        $event = (int) ($data['event'] ?? 0);
        if (!$event) {
            throw new ApiErrorException(500, 'Error creating Thirdparty Notification, request_data missing event');
        }
        $contactId = (int) ($data['contact_id'] ?? 0);
        $type = (string) ($data['type'] ?? 'email');

        if ($this->notificationService->exists($id, $event, $contactId)) {
            throw new ApiErrorException(403, 'Notification already exists');
        }

        $notifId = $this->notificationService->create($id, $event, $contactId, $type);
        if ($notifId < 0) {
            throw new ApiErrorException(500, 'Error creating Thirdparty Notification');
        }
        $this->notificationService->update($notifId, $id, $event, $contactId, $type);

        $notif = $this->notificationService->fetch($notifId);

        return new JsonResponse($notif);
    }

    #[Route('/{id}/notificationsbycode/{code}', name: 'thirdparties_create_notification_by_code', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createCompanyNotificationByCode(int $id, string $code, Request $request): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Error creating Thirdparty Notification, Thirdparty doesn\'t exists');
        }
        $eventId = $this->notificationService->actionTriggerIdForCode($code);
        if ($eventId === null) {
            throw new ApiErrorException(404, 'Action Trigger code not found');
        }
        $data = $this->body($request);
        if (isset($data['event'])) {
            throw new ApiErrorException(500, 'Error creating Thirdparty Notification, request_data contains event key');
        }
        if (isset($data['fk_action'])) {
            throw new ApiErrorException(500, 'Error creating Thirdparty Notification, request_data contains fk_action key');
        }
        $contactId = (int) ($data['contact_id'] ?? 0);
        $type = (string) ($data['type'] ?? 'email');

        if ($this->notificationService->exists($id, $eventId, $contactId)) {
            throw new ApiErrorException(403, 'Notification already exists');
        }

        $notifId = $this->notificationService->create($id, $eventId, $contactId, $type);
        if ($notifId < 0) {
            throw new ApiErrorException(500, 'Error creating Thirdparty Notification, are request_data well formed?');
        }
        $this->notificationService->update($notifId, $id, $eventId, $contactId, $type);

        return new JsonResponse($this->notificationService->fetch($notifId));
    }

    #[Route('/{id}/notifications/{notification_id}', name: 'thirdparties_update_notification', requirements: ['id' => '\d+', 'notification_id' => '\d+'], methods: ['PUT'])]
    public function updateCompanyNotification(int $id, int $notification_id, Request $request): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Error creating Company Notification, Company doesn\'t exists');
        }
        $notif = $this->notificationService->fetch($notification_id, $id);
        if ($notif === null || (int) $notif['socid'] !== $id) {
            throw new ApiErrorException(403, 'Not allowed due to bad consistency of input data');
        }
        $data = $this->body($request);
        $event = (int) ($data['event'] ?? $notif['event']);
        $contactId = (int) ($data['contact_id'] ?? $notif['contact_id']);
        $type = (string) ($data['type'] ?? $notif['type'] ?? 'email');

        if ($this->notificationService->update($notification_id, $id, $event, $contactId, $type) < 0) {
            throw new ApiErrorException(500, 'Error updating values');
        }

        return new JsonResponse($this->notificationService->fetch($notification_id));
    }

    #[Route('/{id}/notifications/{notification_id}', name: 'thirdparties_delete_notification', requirements: ['id' => '\d+', 'notification_id' => '\d+'], methods: ['DELETE'])]
    public function deleteCompanyNotification(int $id, int $notification_id): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Error deleting Company Notification, Company doesn\'t exists');
        }
        $notif = $this->notificationService->fetch($notification_id, $id);
        if ($notif === null || (int) $notif['socid'] !== $id) {
            throw new ApiErrorException(403, 'Not allowed due to bad consistency of input data');
        }
        if ($this->notificationService->delete($notification_id) < 0) {
            throw new ApiErrorException(500, 'Error deleting values');
        }

        return new JsonResponse(['success' => ['code' => 200, 'message' => 'Notification deleted']]);
    }

    // ==================================================================
    //  bankaccounts
    // ==================================================================

    #[Route('/{id}/bankaccounts', name: 'thirdparties_get_bankaccounts', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getCompanyBankAccount(int $id): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }
        $ids = $this->bankAccountService->listForCompany($id);
        if (count($ids) === 0) {
            throw new ApiErrorException(404, 'Account not found');
        }

        $fields = ['socid', 'default_rib', 'frstrecur', 'datec', 'datem', 'label', 'bank', 'bic', 'iban', 'id', 'rum'];
        $ret = [];
        foreach ($ids as $rid) {
            $acc = $this->bankAccountService->fetch($rid);
            if ($acc === null) {
                continue;
            }
            $obj = [];
            $acc['socid'] = $acc['fk_soc'];
            $acc['id'] = $acc['rowid'];
            $acc['datem'] = $acc['tms'];
            foreach ($fields as $k) {
                if (array_key_exists($k, $acc)) {
                    $obj[$k] = $acc[$k];
                }
            }
            $ret[] = $obj;
        }

        return new JsonResponse($ret);
    }

    #[Route('/{id}/bankaccounts', name: 'thirdparties_create_bankaccount', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createCompanyBankAccount(int $id, Request $request): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Error creating Company Bank account, Company doesn\'t exists');
        }
        $data = $this->body($request);

        $accountId = $this->bankAccountService->create($id);
        if ($accountId < 0) {
            throw new ApiErrorException(500, 'Error creating Company Bank account');
        }

        $fields = $data;
        if (empty($fields['rum'])) {
            $fields['rum'] = $this->bankAccountService->buildRumNumber((string) ($c->code_client ?? ''), time(), $accountId);
            $fields['date_rum'] = date('Y-m-d H:i:s');
        }
        if ($this->bankAccountService->update($accountId, $fields) < 0) {
            throw new ApiErrorException(500, 'Error updating values');
        }

        return new JsonResponse($this->bankAccountService->fetch($accountId));
    }

    #[Route('/{id}/bankaccounts/{bankaccount_id}', name: 'thirdparties_update_bankaccount', requirements: ['id' => '\d+', 'bankaccount_id' => '\d+'], methods: ['PUT'])]
    public function updateCompanyBankAccount(int $id, int $bankaccount_id, Request $request): JsonResponse
    {
        $c = $this->load($id);
        if ($c === null) {
            throw new ApiErrorException(404, 'Error creating Company Bank account, Company doesn\'t exists');
        }
        $acc = $this->bankAccountService->fetch($bankaccount_id);
        if ($acc === null || (int) $acc['fk_soc'] !== $id) {
            throw new ApiErrorException(403);
        }
        $fields = $this->body($request);
        unset($fields['caller']);
        if (empty($fields['rum']) && empty($acc['rum'])) {
            $fields['rum'] = $this->bankAccountService->buildRumNumber((string) ($c->code_client ?? ''), $acc['datec'] ?? time(), $bankaccount_id);
            $fields['date_rum'] = date('Y-m-d H:i:s');
        }
        if ($this->bankAccountService->update($bankaccount_id, $fields) < 0) {
            throw new ApiErrorException(500, 'Error updating values');
        }

        return new JsonResponse($this->bankAccountService->fetch($bankaccount_id));
    }

    #[Route('/{id}/bankaccounts/{bankaccount_id}', name: 'thirdparties_delete_bankaccount', requirements: ['id' => '\d+', 'bankaccount_id' => '\d+'], methods: ['DELETE'])]
    public function deleteCompanyBankAccount(int $id, int $bankaccount_id): JsonResponse
    {
        $acc = $this->bankAccountService->fetch($bankaccount_id);
        $socid = $acc === null ? 0 : (int) $acc['fk_soc'];
        if ($socid === $id) {
            $this->bankAccountService->delete($bankaccount_id);

            return new JsonResponse(1);
        }

        throw new ApiErrorException(403, 'Not allowed due to bad consistency of input data');
    }

    // ==================================================================
    //  generateBankAccountDocument — document generation is out of scope
    // ==================================================================

    #[Route('/{id}/generateBankAccountDocument/{companybankid}/{model}', name: 'thirdparties_gen_bank_doc', requirements: ['id' => '\d+', 'companybankid' => '\d+'], methods: ['GET'])]
    public function generateBankAccountDocument(int $id, int $companybankid = 0, string $model = 'sepamandate'): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $sql = 'SELECT rowid FROM llx_societe_rib WHERE fk_soc = '.(int) $id;
        if ($companybankid) {
            $sql .= ' AND rowid = '.(int) $companybankid;
        }
        $rows = $this->db->fetchFirstColumn($sql);
        if (count($rows) === 0) {
            throw new ApiErrorException(404, 'Bank account not found');
        }

        // PDF/ODT document generation (generateDocument) is a UI-domain
        // concern and out of scope for this API-only service: we persist
        // the chosen model like setDocModel() and report success:0.
        if ($this->thirdpartyService->tableExists('llx_societe')) {
            $this->db->executeStatement('UPDATE llx_societe SET model_pdf = ? WHERE rowid = ?', [$model, $id]);
        }

        return new JsonResponse(['success' => 0]);
    }

    // ==================================================================
    //  societe accounts
    // ==================================================================

    #[Route('/{id}/accounts', name: 'thirdparties_get_accounts', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getSocieteAccounts(int $id, Request $request): JsonResponse
    {
        $site = $request->query->get('site');
        $sql = 'SELECT rowid, fk_soc, key_account, site, date_creation, tms FROM llx_societe_account WHERE fk_soc = '.(int) $id;
        if ($site) {
            $sql .= ' AND site = '.$this->db->quote((string) $site);
        }
        $rows = $this->db->fetchAllAssociative($sql);
        if (count($rows) === 0) {
            throw new ApiErrorException(404, 'This thirdparty does not have any account attached or does not exist.');
        }

        $fields = ['id', 'fk_soc', 'key_account', 'site', 'date_creation', 'tms'];
        $ret = [];
        foreach ($rows as $row) {
            $full = $this->societeAccountService->fetch((int) $row['rowid']);
            if ($full === null) {
                continue;
            }
            $full['id'] = $full['rowid'];
            $ret[] = array_intersect_key($full, array_flip($fields));
        }

        return new JsonResponse($ret);
    }

    #[Route('/accounts/{site}/{key_account}', name: 'thirdparties_get_by_account', methods: ['GET'], priority: 10)]
    public function getSocieteByAccounts(string $site, string $key_account): JsonResponse
    {
        $row = $this->db->fetchAssociative(
            'SELECT rowid, fk_soc, key_account, site, date_creation, tms FROM llx_societe_account'
            .' WHERE site = '.$this->db->quote($site).' AND key_account = '.$this->db->quote($key_account)
            .' AND entity IN ('.$this->config->getEntity('societe').')',
        );
        if ($row === false) {
            throw new ApiErrorException(404, 'This account have many thirdparties attached or does not exist.');
        }

        return $this->doFetch((int) $row['fk_soc']);
    }

    #[Route('/{id}/accounts', name: 'thirdparties_create_account', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createSocieteAccount(int $id, Request $request): JsonResponse
    {
        $data = $this->body($request);
        if (!isset($data['site'])) {
            throw new ApiErrorException(422, 'Unprocessable Entity: You must pass the site attribute in your request data !');
        }
        $exists = $this->db->fetchOne(
            'SELECT rowid FROM llx_societe_account WHERE fk_soc = '.(int) $id.' AND site = '.$this->db->quote((string) $data['site']),
        );
        if ($exists === false) {
            $data['fk_soc'] = $id;
            if (!isset($data['login'])) {
                $data['login'] = '';
            }
            $newId = $this->societeAccountService->create($data);
            if ($newId < 0) {
                throw new ApiErrorException(500, 'Error creating SocieteAccount entity. Ensure that the ID of thirdparty provided does exist!');
            }

            return new JsonResponse($this->societeAccountService->fetch($newId));
        }

        throw new ApiErrorException(409, 'A SocieteAccount entity already exists for this company and site.');
    }

    #[Route('/{id}/accounts/{site}', name: 'thirdparties_post_account', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function postSocieteAccount(int $id, string $site, Request $request): JsonResponse
    {
        $data = $this->body($request);
        $row = $this->db->fetchAssociative(
            'SELECT rowid, fk_user_creat, date_creation FROM llx_societe_account WHERE fk_soc = '.(int) $id.' AND site = '.$this->db->quote($site),
        );

        if ($row === false) {
            // create new
            if (!isset($data['key_account'])) {
                throw new ApiErrorException(422, 'Unprocessable Entity: You must pass the key_account attribute in your request data !');
            }
            $data['fk_soc'] = $id;
            $data['site'] = $site;
            if (!isset($data['login'])) {
                $data['login'] = '';
            }
            $newId = $this->societeAccountService->create($data);
            if ($newId < 0) {
                throw new ApiErrorException(500, 'Error creating SocieteAccount entity.');
            }

            return new JsonResponse($this->societeAccountService->fetch($newId));
        }

        // replace existing
        if (isset($data['site']) && $data['site'] !== $site) {
            $dup = $this->db->fetchOne(
                'SELECT rowid FROM llx_societe_account WHERE fk_soc = '.(int) $id.' AND site = '.$this->db->quote((string) $data['site']),
            );
            if ($dup !== false) {
                throw new ApiErrorException(409, 'You are trying to update this thirdparty Account for '.$site.' to '.$data['site'].' but another Account already exists with this site key.');
            }
        }
        $data['fk_soc'] = $id;
        $data['site'] = $site;
        $data['fk_user_creat'] = $row['fk_user_creat'];
        $data['date_creation'] = $row['date_creation'];
        if (!isset($data['login'])) {
            $data['login'] = '';
        }
        if ($this->societeAccountService->update((int) $row['rowid'], $data) < 0) {
            throw new ApiErrorException(500, 'Error updating SocieteAccount entity.');
        }

        return new JsonResponse($this->societeAccountService->fetch((int) $row['rowid']));
    }

    #[Route('/{id}/accounts/{site}', name: 'thirdparties_put_account', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function putSocieteAccount(int $id, string $site, Request $request): JsonResponse
    {
        $data = $this->body($request);
        $row = $this->db->fetchAssociative(
            'SELECT rowid FROM llx_societe_account WHERE fk_soc = '.(int) $id.' AND site = '.$this->db->quote($site),
        );
        if ($row === false) {
            throw new ApiErrorException(404, 'This thirdparty does not have '.$site.' account attached or does not exist.');
        }
        if (isset($data['site']) && $data['site'] !== $site) {
            $dup = $this->db->fetchOne(
                'SELECT rowid FROM llx_societe_account WHERE fk_soc = '.(int) $id.' AND site = '.$this->db->quote((string) $data['site']),
            );
            if ($dup !== false) {
                throw new ApiErrorException(409, 'You are trying to update this thirdparty Account for '.$site.' to '.$data['site'].' but another Account already exists with this thirdparty with this site key.');
            }
        }
        if ($this->societeAccountService->update((int) $row['rowid'], $data) < 0) {
            throw new ApiErrorException(500, 'Error updating SocieteAccount account');
        }

        return new JsonResponse($this->societeAccountService->fetch((int) $row['rowid']));
    }

    #[Route('/{id}/accounts/{site}', name: 'thirdparties_delete_account', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteSocieteAccount(int $id, string $site): JsonResponse
    {
        $rowid = $this->db->fetchOne(
            'SELECT rowid FROM llx_societe_account WHERE fk_soc = '.(int) $id.' AND site = '.$this->db->quote($site),
        );
        if ($rowid === false) {
            throw new ApiErrorException(404);
        }
        $this->societeAccountService->delete((int) $rowid);

        return new JsonResponse(null);
    }

    #[Route('/{id}/accounts', name: 'thirdparties_delete_accounts', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteSocieteAccounts(int $id): JsonResponse
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT rowid, fk_soc, key_account, site, date_creation, tms FROM llx_societe_account WHERE fk_soc = '.(int) $id,
        );
        if (count($rows) === 0) {
            throw new ApiErrorException(404, 'This third party does not have any account attached or does not exist.');
        }
        foreach ($rows as $row) {
            $this->societeAccountService->delete((int) $row['rowid']);
        }

        return new JsonResponse(null);
    }

    // ==================================================================
    //  helpers
    // ==================================================================

    private function userExists(int $id): bool
    {
        return $this->db->fetchOne('SELECT rowid FROM llx_user WHERE rowid = ?', [$id]) !== false;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);
        if (is_array($data)) {
            return $data;
        }

        return $request->request->all() ?: $request->query->all();
    }

    /** Port of DoliDB::order() */
    private function orderBy(string $sortfield, string $sortorder): string
    {
        if ($sortfield === '') {
            return '';
        }
        $sortfield = (string) preg_replace('/[a-z_]+\([^\)]*\) as ([\w]+)/i', '\1', $sortfield);
        $fields = explode(',', $sortfield);
        $orders = $sortorder !== '' ? explode(',', $sortorder) : [];
        $return = '';
        $oldsortorder = '';
        $i = 0;
        foreach ($fields as $val) {
            $fieldname = (string) preg_replace('/[^0-9a-z_\.]/i', '', $val);
            if ($fieldname === '') {
                continue;
            }
            $return .= $return === '' ? ' ORDER BY ' : ', ';
            $return .= $fieldname;
            $tmpsortorder = empty($orders[$i]) ? '' : trim($orders[$i]);
            if (strtoupper($tmpsortorder) === 'ASC') {
                $oldsortorder = 'ASC';
                $return .= ' ASC';
            } elseif (strtoupper($tmpsortorder) === 'DESC') {
                $oldsortorder = 'DESC';
                $return .= ' DESC';
            } else {
                $return .= ' '.($oldsortorder !== '' ? $oldsortorder : 'ASC');
            }
            $i++;
        }

        return $return;
    }
}
