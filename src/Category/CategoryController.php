<?php

declare(strict_types=1);

namespace App\Category;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of htdocs/categories/class/api_categories.class.php.
 *
 * Dolibarr dispatches these endpoints under /api/index.php/categories; here
 * they live under /api/categories. Response bodies and error statuses/messages
 * are replicated verbatim for the CRM slice (category types: customer,
 * supplier, contact).
 *
 * Upstream permission checks (hasRight('categorie', ...), per-module rights on
 * link/unlink) are satisfied by the API-key firewall: every authenticated
 * request maps to a full-rights internal service user. _checkAccessToResource()
 * reduces to the entity check implemented in CategoryService::isInEntityScope()
 * and checkAccessToLinkedObject().
 */
#[Route('/api/categories')]
final class CategoryController extends AbstractController
{
    /** Upstream Categories::$FIELDS — mandatory on POST. */
    private const FIELDS = ['label', 'type'];

    /**
     * Properties that may not be set through the API
     * (upstream _checkValForAPI forbidden list).
     */
    private const FORBIDDEN_FIELDS = [
        'db', 'table_element', 'table_rowid', 'table_ref_field', 'table_element_line', 'element', 'fk_element',
        'element_for_permission', 'class_element_line',
        'fields', 'TRIGGER_PREFIX', 'picto',
        'restrictiononfksoc', 'ismultientitymanaged', 'isextrafieldmanaged',
        'module', 'error', 'errorhidden', 'errors', 'warning', 'warnings', 'validateFieldsErrors',
        'oldcopy', 'oldref', 'newref', 'context',
        'actionmsg', 'actionmsg2', 'thirdparty', 'user',
        'tpl', 'extraparams',
        'childtables', 'childtablesoncascade',
    ];

    /**
     * Categorie::$fields 'type' hints driving _checkValForAPI sanitization.
     * Only entries that change behavior are listed (int/double-like -> int).
     */
    private const FIELD_TYPES = [
        'rowid' => 'integer',
        'entity' => 'integer',
        'fk_parent' => 'integer',
        'label' => 'varchar(180)',
        'ref_ext' => 'varchar(255)',
        'type' => 'integer',
        'description' => 'text',
        'color' => 'varchar(8)',
        'position' => 'integer',
        'fk_soc' => 'integer:Societe:societe/class/societe.class.php',
        'visible' => 'integer',
        'import_key' => 'varchar(14)',
        'date_creation' => 'datetime',
        'tms' => 'timestamp',
        'fk_user_creat' => 'integer:User:user/class/user.class.php',
        'fk_user_modif' => 'integer:User:user/class/user.class.php',
    ];

    /**
     * Types recognized by each upstream link/unlink endpoint — the upstream
     * lists are asymmetric (linkObjectById accepts 'project', the other three
     * endpoints do not), so they are replicated verbatim. Types without a
     * backing object table in this schema fail on fetch like upstream would
     * when the module is absent.
     */
    private const LINKABLE_TYPES_BY_ID = [
        'product', 'customer', 'supplier', 'contact', 'member', 'actioncomm', 'project',
    ];
    private const LINKABLE_TYPES_BY_REF = ['product', 'customer', 'supplier', 'contact', 'member', 'actioncomm'];

    public function __construct(
        private readonly CategoryService $service,
        private readonly CategorySerializer $serializer,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
        private readonly UniversalSearchFilter $searchFilter,
    ) {
    }

    // ==================================================================
    //  GET /api/categories — index()
    // ==================================================================

    #[Route('', name: 'categories_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $sortfield = (string) ($request->query->get('sortfield') ?? 't.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 100);
        $page = (int) ($request->query->get('page') ?? 0);
        $type = (string) ($request->query->get('type') ?? '');
        $sqlfilters = (string) ($request->query->get('sqlfilters') ?? '');
        $properties = (string) ($request->query->get('properties') ?? '');

        $sqlfiltersFragment = '';
        if ($sqlfilters !== '') {
            $errormessage = '';
            $sqlfiltersFragment = $this->searchFilter->forge($sqlfilters, $errormessage);
            if ($errormessage !== '') {
                throw new ApiErrorException(400, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
        }

        $rows = $this->service->listIds($type, $sqlfiltersFragment, $sortfield, $sortorder, $limit, $page);
        if ($rows === null) {
            throw new ApiErrorException(503, 'Error when retrieve category list : ' . $this->service->getLastError());
        }

        $objRet = [];
        $num = count($rows);
        $min = min($num, ($limit <= 0 ? $num : $limit));
        for ($i = 0; $i < $min; $i++) {
            $cat = $this->service->fetch((int) $rows[$i]['rowid']);
            if ($cat !== null) {
                $objRet[] = $this->serializer->filterProperties($this->serializer->toArray($cat), $properties);
            }
        }

        return new JsonResponse($objRet);
    }

    // ==================================================================
    //  GET /api/categories/types — getTypes()
    // ==================================================================

    #[Route('/types', name: 'categories_types', methods: ['GET'])]
    public function getTypes(): JsonResponse
    {
        return new JsonResponse(CategoryService::MAP_TYPE_TITLE_AREA);
    }

    // ==================================================================
    //  GET /api/categories/object/{type}/{id} — getListForObject()
    // ==================================================================

    #[Route('/object/{type}/{id}', name: 'categories_object_list', methods: ['GET'])]
    public function getListForObject(string $type, string $id, Request $request): JsonResponse
    {
        $sortfield = (string) ($request->query->get('sortfield') ?? 's.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 0);
        $page = (int) ($request->query->get('page') ?? 0);

        if (
            !in_array($type, [
            'product', 'contact', 'customer', 'supplier', 'member', 'project',
            'knowledgemanagement', 'actioncomm', 'user', 'warehouse', 'ticket', 'fichinter',
            ], true)
        ) {
            throw new ApiErrorException(403);
        }

        $categories = $this->service->getListForItem((int) $id, $type, $sortfield, $sortorder, $limit, $page);

        if (!is_array($categories)) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(600, 'Error when fetching object categories', array_values($errors));
        }

        return new JsonResponse($categories);
    }

    // ==================================================================
    //  GET /api/categories/{id} — get()
    // ==================================================================

    #[Route('/{id}', name: 'categories_get', methods: ['GET'])]
    public function get(string $id, Request $request): JsonResponse
    {
        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkAccessToResource($cat);

        $childs = [];
        if ($request->query->getBoolean('include_childs')) {
            $childRows = $this->service->getFilles((int) $cat['rowid']);
            if ($childRows === null) {
                $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
                throw new ApiErrorException(500, 'Error when fetching child categories', array_values($errors));
            }
            foreach ($childRows as $childRow) {
                $childs[] = $this->serializer->toArray($childRow);
            }
        }

        return new JsonResponse($this->serializer->toArray($cat, $childs));
    }

    // ==================================================================
    //  POST /api/categories — post()
    // ==================================================================

    #[Route('', name: 'categories_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $requestData = $this->decodeBody($request);

        // _validate(): mandatory fields
        foreach (self::FIELDS as $field) {
            if (!isset($requestData[$field])) {
                throw new ApiErrorException(400, $field . ' field missing');
            }
        }

        $props = [];
        foreach ($requestData as $field => $value) {
            if ($field === 'caller') {
                // Mention of the caller for sync-back-loop protection (no-op here)
                $this->utils->sanitizeVal($value, 'aZ09');
                continue;
            }

            $props[$field] = $this->checkValForAPI($field, $value);
        }

        $id = $this->service->create($props, $this->config->apiUserId());
        if ($id < 0) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when creating category', array_values($errors));
        }

        return new JsonResponse($id);
    }

    // ==================================================================
    //  PUT /api/categories/{id} — put()
    // ==================================================================

    #[Route('/{id}', name: 'categories_put', methods: ['PUT'])]
    public function put(string $id, Request $request): JsonResponse
    {
        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkAccessToResource($cat);

        $requestData = $this->decodeBody($request);

        $props = [];
        foreach ($requestData as $field => $value) {
            if ($field === 'id') {
                continue;
            }
            if ($field === 'caller') {
                $this->utils->sanitizeVal($value, 'aZ09');
                continue;
            }

            if ($field === 'array_options' && is_array($value)) {
                $arrayOptions = [];
                foreach ($value as $index => $val) {
                    $arrayOptions[$index] = $this->checkValExtrafieldsForAPI((string) $index, $val);
                }
                $props['array_options'] = $arrayOptions;
                continue;
            }

            $props[$field] = $this->checkValForAPI($field, $value);
        }

        $result = $this->service->update($cat, $props, $this->config->apiUserId());
        if ($result > 0) {
            return $this->get($id, $request);
        }

        throw new ApiErrorException(500, (string) $this->service->getLastError());
    }

    // ==================================================================
    //  DELETE /api/categories/{id} — delete()
    // ==================================================================

    #[Route('/{id}', name: 'categories_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkAccessToResource($cat);

        if ($this->service->delete($cat) <= 0) {
            throw new ApiErrorException(500, 'Error when delete category : ' . $this->service->getLastError());
        }

        return new JsonResponse([
            'success' => [
                'code' => 200,
                'message' => 'Category deleted',
            ],
        ]);
    }

    // ==================================================================
    //  GET /api/categories/{id}/objects — getObjects()
    // ==================================================================

    #[Route('/{id}/objects', name: 'categories_objects', methods: ['GET'])]
    public function getObjects(string $id, Request $request): JsonResponse
    {
        $type = (string) ($request->query->get('type') ?? '');
        $onlyids = (int) ($request->query->get('onlyids') ?? 0);

        if ($type === '') {
            throw new ApiErrorException(500, 'The "type" parameter is required.');
        }

        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkAccessToResource($cat);

        $result = $this->service->getObjectsInCateg((int) $cat['rowid'], $type, $onlyids);
        if (!is_array($result)) {
            throw new ApiErrorException(503, 'Error when retrieving objects list : ' . $this->service->getLastError());
        }

        if ($onlyids) {
            return new JsonResponse($result);
        }

        // Upstream returns each object's API class _cleanObjectDatas() output;
        // the object APIs live in their own slices, so we return the cleaned
        // row shape here (rowid -> id).
        $objects = [];
        foreach ($result as $row) {
            if (!is_array($row)) {
                continue;
            }
            $object = $row;
            $object['id'] = (int) ($row['rowid'] ?? $row['id'] ?? 0);
            unset($object['rowid']);
            $objects[] = $object;
        }

        return new JsonResponse($objects);
    }

    // ==================================================================
    //  POST /api/categories/{id}/objects/{type}/{objectId} — linkObjectById()
    // ==================================================================

    #[Route('/{id}/objects/{type}/{objectId}', name: 'categories_link_object', methods: ['POST'])]
    public function linkObjectById(string $id, string $type, string $objectId): JsonResponse
    {
        if ($type === '' || empty($objectId)) {
            throw new ApiErrorException(403);
        }

        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkLinkableType($type, self::LINKABLE_TYPES_BY_ID);

        $object = $this->service->fetchObjectById($type, (int) $objectId);
        if ($object === null) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when fetching object', array_values($errors));
        }

        $this->checkAccessToLinkedObject($type, $object);

        $result = $this->service->addType((int) $cat['rowid'], (int) ($object['rowid'] ?? $object['id']), $type);
        if ($result < 0 && $this->service->getLastError() !== 'DB_ERROR_RECORD_ALREADY_EXISTS') {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when linking object', array_values($errors));
        }

        return new JsonResponse([
            'success' => [
                'code' => 200,
                'message' => 'Objects successfully linked to the category',
            ],
        ]);
    }

    // ==================================================================
    //  POST /api/categories/{id}/objects/{type}/ref/{objectRef} — linkObjectByRef()
    // ==================================================================

    #[Route('/{id}/objects/{type}/ref/{objectRef}', name: 'categories_link_object_ref', methods: ['POST'])]
    public function linkObjectByRef(string $id, string $type, string $objectRef): JsonResponse
    {
        if ($type === '' || $objectRef === '') {
            throw new ApiErrorException(403);
        }

        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkLinkableType($type, self::LINKABLE_TYPES_BY_REF);

        $object = $this->service->fetchObjectByRef($type, $objectRef);
        if ($object === null) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when fetching object', array_values($errors));
        }

        $this->checkAccessToLinkedObject($type, $object);

        $result = $this->service->addType((int) $cat['rowid'], (int) ($object['rowid'] ?? $object['id']), $type);
        if ($result < 0 && $this->service->getLastError() !== 'DB_ERROR_RECORD_ALREADY_EXISTS') {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when linking object', array_values($errors));
        }

        return new JsonResponse([
            'success' => [
                'code' => 200,
                'message' => 'Objects successfully linked to the category',
            ],
        ]);
    }

    // ==================================================================
    //  DELETE /api/categories/{id}/objects/{type}/{objectId} — unlinkObjectById()
    // ==================================================================

    #[Route('/{id}/objects/{type}/{objectId}', name: 'categories_unlink_object', methods: ['DELETE'])]
    public function unlinkObjectById(string $id, string $type, string $objectId): JsonResponse
    {
        if ($type === '' || empty($objectId)) {
            throw new ApiErrorException(403);
        }

        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkLinkableType($type, self::LINKABLE_TYPES_BY_REF);

        $object = $this->service->fetchObjectById($type, (int) $objectId);
        if ($object === null) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when fetching object', array_values($errors));
        }

        $this->checkAccessToLinkedObject($type, $object);

        $result = $this->service->delType((int) $cat['rowid'], (int) ($object['rowid'] ?? $object['id']), $type);
        if ($result < 0) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when unlinking object', array_values($errors));
        }

        return new JsonResponse([
            'success' => [
                'code' => 200,
                'message' => 'Objects successfully unlinked from the category',
            ],
        ]);
    }

    // ==================================================================
    //  DELETE /api/categories/{id}/objects/{type}/ref/{objectRef} — unlinkObjectByRef()
    // ==================================================================

    #[Route('/{id}/objects/{type}/ref/{objectRef}', name: 'categories_unlink_object_ref', methods: ['DELETE'])]
    public function unlinkObjectByRef(string $id, string $type, string $objectRef): JsonResponse
    {
        if ($type === '' || $objectRef === '') {
            throw new ApiErrorException(403);
        }

        $cat = $this->service->fetch((int) $id);
        if ($cat === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        $this->checkLinkableType($type, self::LINKABLE_TYPES_BY_REF);

        $object = $this->service->fetchObjectByRef($type, $objectRef);
        if ($object === null) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when fetching object', array_values($errors));
        }

        $this->checkAccessToLinkedObject($type, $object);

        $result = $this->service->delType((int) $cat['rowid'], (int) ($object['rowid'] ?? $object['id']), $type);
        if ($result < 0) {
            $errors = array_filter([$this->service->getLastError(), ...$this->service->getErrors()]);
            throw new ApiErrorException(500, 'Error when unlinking object', array_values($errors));
        }

        return new JsonResponse([
            'success' => [
                'code' => 200,
                'message' => 'Objects successfully unlinked from the category',
            ],
        ]);
    }

    // ==================================================================
    //  internals
    // ==================================================================

    /** @return array<string, mixed> */
    private function decodeBody(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * _checkAccessToResource('categorie', id) — entity scope check.
     *
     * @param array<string, mixed> $cat
     */
    private function checkAccessToResource(array $cat): void
    {
        if (!$this->service->isInEntityScope($cat)) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }
    }

    /**
     * Type dispatch of upstream link/unlink endpoints. Types not recognized
     * upstream throw 400 "this type is not recognized yet."
     */
    /**
     * @param list<string> $linkableTypes
     */
    private function checkLinkableType(string $type, array $linkableTypes): void
    {
        if (!in_array($type, $linkableTypes, true)) {
            throw new ApiErrorException(400, 'this type is not recognized yet.');
        }
    }

    /**
     * _checkAccessToLinkedObject() — 403 when the linked object is out of
     * the caller's entity scope.
     *
     * @param array<string, mixed> $object
     */
    private function checkAccessToLinkedObject(string $type, array $object): void
    {
        if (!$this->service->checkAccessToLinkedObject($type, $object)) {
            $objectId = (int) ($object['rowid'] ?? $object['id'] ?? 0);
            throw new ApiErrorException(
                403,
                'Access to ' . $type . ' ' . $objectId . ' not allowed for login ' . $this->config->apiUserLogin()
            );
        }
    }

    /**
     * Port of DolibarrApi::_checkValForAPI() against Categorie::$fields.
     */
    private function fieldType(string $field): ?string
    {
        return self::FIELD_TYPES[$field] ?? null;
    }

    private function checkValForAPI(string $field, mixed $value): mixed
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            throw new ApiErrorException(400, 'Parameter ' . $field . ' is not allowed in request');
        }

        if (!is_array($value)) {
            if (in_array($field, self::FORBIDDEN_FIELDS, true)) {
                throw new ApiErrorException(400, 'Parameter ' . $field . ' is not allowed in request');
            }

            $type = $this->fieldType($field);
            if ($type !== null) {
                if (
                    str_starts_with($type, 'int') || str_starts_with($type, 'double')
                    || in_array($type, ['real', 'price', 'stock'], true)
                ) {
                    return $this->utils->sanitizeVal($value, 'int');
                }
                if ($type === 'html') {
                    return $this->utils->sanitizeVal($value, 'restricthtml');
                }
                if (
                    $type === 'select' || $type === 'sellist' || $type === 'checkbox'
                    || $type === 'boolean' || $type === 'radio'
                ) {
                    return $this->utils->sanitizeVal($value, 'alphanohtml');
                }
                if ($type === 'email') {
                    return $this->utils->sanitizeVal($value, 'email');
                }
                if ($type === 'password') {
                    return $this->utils->sanitizeVal($value, 'password');
                }
            }

            if (preg_match('/^fk_/i', $field)) {
                return $this->utils->sanitizeVal($value, 'int');
            }
            if (in_array($field, ['note', 'note_private', 'note_public', 'desc', 'description'], true)) {
                return $this->utils->sanitizeVal($value, 'restricthtml');
            }

            return $this->utils->sanitizeVal($value, 'alphanohtml');
        }

        $newarrayvalue = [];
        foreach ($value as $tmpkey => $tmpvalue) {
            $newarrayvalue[$tmpkey] = $this->checkValForAPI((string) $tmpkey, $tmpvalue);
        }

        return $newarrayvalue;
    }

    /**
     * Port of DolibarrApi::_checkValExtrafieldsForAPI(). This schema defines
     * no extra field attributes on llx_categories_extrafields, so every value
     * is sanitized with the 'alphanohtml' fallback like upstream.
     */
    private function checkValExtrafieldsForAPI(string $field, mixed $value): mixed
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            throw new ApiErrorException(400, 'Parameter ' . $field . ' is not allowed in request');
        }

        if (!is_array($value)) {
            return $this->utils->sanitizeVal($value, 'alphanohtml');
        }

        $newarrayvalue = [];
        foreach ($value as $tmpkey => $tmpvalue) {
            $newarrayvalue[$tmpkey] = $this->checkValExtrafieldsForAPI((string) $tmpkey, $tmpvalue);
        }

        return $newarrayvalue;
    }
}
