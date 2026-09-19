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
        $sql .= ' WHERE t.entity IN (' . $this->config->getEntity('societe') . ')';
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
                $sql .= ' AND c.fk_categorie = ' . $category . ' AND c.fk_soc = t.rowid';
            } elseif ($mode === 4) {
                $sql .= ' AND cc.fk_categorie = ' . $category . ' AND cc.fk_soc = t.rowid';
            } else {
                $sql .= ' AND ((c.fk_categorie = ' . $category . ' AND c.fk_soc = t.rowid) OR (cc.fk_categorie = ' . $category . ' AND cc.fk_soc = t.rowid))';
            }
        }
        if ($socids !== '') {
            $sql .= ' AND t.rowid IN (' . implode(',', array_map('intval', explode(',', $socids))) . ')';
        }
        if ($searchSale && $searchSale != -1) {
            if ($searchSale == -2) {
                $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE sc.fk_soc = t.rowid AND sc.fk_user IS NULL)';
            } elseif ($searchSale > 0) {
                $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE sc.fk_soc = t.rowid AND sc.fk_user = ' . (int) $searchSale . ')';
            }
        }
        if ($sqlfilters !== '') {
            $errormessage = '';
            $sql .= (new UniversalSearchFilter($this->db))->forge($sqlfilters, $errormessage);
            if ($errormessage !== '') {
                throw new ApiErrorException(400, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
        }

        $sqlTotals = str_replace('SELECT t.rowid', 'SELECT count(t.rowid) as total', $sql);

        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $sql .= ' LIMIT ' . ($limit + 1) . ' OFFSET ' . ($limit * $page);
        }

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            throw new ApiErrorException(503, 'Error when retrieve third parties : ' . $e->getMessage());
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
            $this->discountService->getAvailableDiscounts($c, $filterabsolute),
            'MT'
        );
        $c->absolute_creditnote = (float) $this->utils->price2num(
            $this->discountService->getAvailableDiscounts($c, $filtercreditnote),
            'MT'
        );

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
            $countryId = $this->utils->getIdFromCode((string) $requestData['country_code'], 'c_country', $field, 'rowid', 1);
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
            throw new ApiErrorException(500, 'Error failed to merged thirdparty ' . $idtodelete . ' into ' . $id . '. Enable and read log file for more information.');
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
            throw new ApiErrorException(400, 'Price level must be between 1 and ' . $this->config->getString('PRODUIT_MULTIPRICES_LIMIT'));
        }

        // upstream: fetch()<0 -> 404 'not found', fetch()==0 -> 500 'Error fetching'
        $c = new Company();
        $fetch = $this->thirdpartyService->fetch($c, $id);
        if ($fetch < 0) {
            throw new ApiErrorException(404, 'Thirdparty ' . $id . ' not found');
        }
        if ($fetch === 0) {
            throw new ApiErrorException(500, 'Error fetching thirdparty ' . $id, [$this->thirdpartyService->error]);
        }

        $result = $this->thirdpartyService->setPriceLevel($c, $priceLevel);
        if ($result <= 0) {
            throw new ApiErrorException(500, 'Error setting new price level for thirdparty ' . $id, [$this->thirdpartyService->error]);
        }

        return new JsonResponse($this->serializer->toArray($c));
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
            throw new ApiErrorException(503, 'Error when retrieve categories : ' . $this->categoryService->getLastError());
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

        $table = match ($kind) {
            'propal' => 'propal',
            'commande' => 'commande',
            default => 'facture',
        };

        if ($this->thirdpartyService->tableExists('llx_' . $table)) {
            $sql = 'SELECT rowid, ref, total_ht, total_ttc, fk_statut as status FROM llx_' . $table . ' as f WHERE fk_soc = ' . (int) $id;
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
        // upstream: PHP arrays — empty serializes [], int-keyed serializes {id: ref}
        return new JsonResponse(['opened' => $opened, 'refs' => $refs, 'refsopened' => $refsOpened]);
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
    //  generateBankAccountDocument — document generation is out of scope
    // ==================================================================

    #[Route('/{id}/generateBankAccountDocument/{companybankid}/{model}', name: 'thirdparties_gen_bank_doc', requirements: ['id' => '\d+', 'companybankid' => '\d+'], methods: ['GET'])]
    public function generateBankAccountDocument(int $id, int $companybankid = 0, string $model = 'sepamandate'): JsonResponse
    {
        if ($this->load($id) === null) {
            throw new ApiErrorException(404, 'Thirdparty not found');
        }

        $sql = 'SELECT rowid FROM llx_societe_rib WHERE fk_soc = ' . (int) $id;
        if ($companybankid) {
            $sql .= ' AND rowid = ' . (int) $companybankid;
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

    #[Route('/accounts/{site}/{key_account}', name: 'thirdparties_get_by_account', methods: ['GET'], priority: 10)]
    public function getSocieteByAccounts(string $site, string $key_account): JsonResponse
    {
        // upstream requires num_rows == 1 — zero or multiple matches both 404
        $rows = $this->db->fetchAllAssociative(
            'SELECT rowid, fk_soc, key_account, site, date_creation, tms FROM llx_societe_account'
            . ' WHERE site = ' . $this->db->quote($site) . ' AND key_account = ' . $this->db->quote($key_account)
            . ' AND entity IN (' . $this->config->getEntity('societe') . ')',
        );
        if (count($rows) !== 1) {
            throw new ApiErrorException(404, 'This account have many thirdparties attached or does not exist.');
        }

        return $this->doFetch((int) $rows[0]['fk_soc']);
    }

    // ==================================================================
    //  helpers
    // ==================================================================

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
                $return .= ' ' . ($oldsortorder !== '' ? $oldsortorder : 'ASC');
            }
            $i++;
        }

        return $return;
    }
}
