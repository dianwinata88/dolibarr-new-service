<?php

declare(strict_types=1);

namespace App\Contact;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of htdocs/societe/class/api_contacts.class.php plus the contact
 * sub-resource of api_thirdparties (GET /api/thirdparties/{id}/contacts)
 * and the contact dictionaries of api_setup
 * (GET /api/setup/dictionary/contact_types, /api/setup/dictionary/civilities).
 *
 * Dolibarr dispatches these endpoints under /api/index.php/contacts; here
 * they live under /api/contacts. Response bodies and error statuses/messages
 * are replicated verbatim.
 *
 * Upstream permission checks (hasRight('societe', 'contact', 'lire'|'creer'|
 * 'supprimer') and hasRight('categorie', 'lire')) are satisfied by the API-key
 * firewall: every authenticated request maps to a full-rights internal service
 * user. Individual rights can be flipped through DOLIBARR_API_RIGHT_* env vars;
 * _checkAccessToResource() is emulated through DOLIBARR_API_SOCID (external
 * user) and DOLIBARR_API_RIGHT_SOCIETE_CLIENT_VOIR.
 *
 * NOT ported: POST {id}/createUser (creates an llx_user through the full
 * upstream User object — identity domain, llx_user is a stub here).
 */
final class ContactController extends AbstractController
{
    /** Mandatory fields for POST (Contacts::$FIELDS). */
    private const MANDATORY_FIELDS = ['lastname'];

    /**
     * Contact::$fields 'type' values by field name — used by
     * _checkValForAPI() to pick the sanitizeVal() mode.
     *
     */
    private const FIELDS_TYPES = [
        'rowid' => 'integer', 'entity' => 'integer', 'ref_ext' => 'varchar(255)',
        'civility' => 'varchar(6)', 'lastname' => 'varchar(50)', 'name_alias' => 'varchar(255)',
        'firstname' => 'varchar(50)', 'poste' => 'varchar(80)', 'address' => 'varchar(255)',
        'zip' => 'varchar(25)', 'town' => 'varchar(50)', 'fk_departement' => 'integer',
        'fk_pays' => 'integer', 'fk_soc' => 'integer:Societe:societe/class/societe.class.php',
        'birthday' => 'date', 'phone' => 'varchar(30)', 'phone_perso' => 'varchar(30)',
        'phone_mobile' => 'varchar(30)', 'fax' => 'varchar(30)', 'email' => 'varchar(255)',
        'socialnetworks' => 'text', 'photo' => 'varchar(255)', 'priv' => 'smallint(6)',
        'fk_stcommcontact' => 'integer', 'fk_prospectcontactlevel' => 'varchar(12)',
        'note_private' => 'html', 'note_public' => 'html', 'default_lang' => 'varchar(6)',
        'canvas' => 'varchar(32)', 'ip' => 'ip', 'datec' => 'datetime', 'tms' => 'timestamp',
        'fk_user_creat' => 'integer:User:user/class/user.class.php',
        'fk_user_modif' => 'integer:User:user/class/user.class.php',
        'statut' => 'tinyint(4)', 'import_key' => 'varchar(14)',
    ];

    /** Declared field type for $field, or '' when the field is not declared. */
    private static function fieldType(string $field): string
    {
        return self::FIELDS_TYPES[$field] ?? '';
    }

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
        private readonly ContactService $contactService,
        private readonly ContactCategoryService $categoryService,
        private readonly ContactSerializer $serializer,
    ) {
    }

    // ==================================================================
    //  GET /api/contacts — index()
    // ==================================================================

    #[Route('/api/contacts', name: 'contacts_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'lire', 'No permission to read contacts');

        $sortfield = (string) ($request->query->get('sortfield') ?? 't.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 100);
        $page = (int) ($request->query->get('page') ?? 0);
        $thirdpartyIds = (string) ($request->query->get('thirdparty_ids') ?? '');
        $category = (int) ($request->query->get('category') ?? 0);
        $sqlfilters = (string) ($request->query->get('sqlfilters') ?? '');
        $includecount = (int) ($request->query->get('includecount') ?? 0);
        $includeroles = (int) ($request->query->get('includeroles') ?? 0);
        $properties = (string) ($request->query->get('properties') ?? '');
        $paginationData = $request->query->getBoolean('pagination_data', false);

        return $this->doIndex(
            $thirdpartyIds,
            $category,
            $includecount,
            $includeroles,
            $properties,
            $paginationData,
            $sortfield,
            $sortorder,
            $limit,
            $page,
            $sqlfilters
        );
    }

    /**
     * Contact sub-resource of api_thirdparties: list the contacts of one
     * thirdparty (equivalent to index() with thirdparty_ids = id).
     */
    #[Route('/api/thirdparties/{id}/contacts', name: 'thirdparties_contacts', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function thirdpartyContacts(int $id, Request $request): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'lire', 'No permission to read contacts');

        $sortfield = (string) ($request->query->get('sortfield') ?? 't.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 100);
        $page = (int) ($request->query->get('page') ?? 0);
        $sqlfilters = (string) ($request->query->get('sqlfilters') ?? '');
        $includecount = (int) ($request->query->get('includecount') ?? 0);
        $includeroles = (int) ($request->query->get('includeroles') ?? 0);
        $properties = (string) ($request->query->get('properties') ?? '');
        $paginationData = $request->query->getBoolean('pagination_data', false);

        return $this->doIndex(
            (string) $id,
            0,
            $includecount,
            $includeroles,
            $properties,
            $paginationData,
            $sortfield,
            $sortorder,
            $limit,
            $page,
            $sqlfilters
        );
    }

    private function doIndex(
        string $thirdpartyIds,
        int $category,
        int $includecount,
        int $includeroles,
        string $properties,
        bool $paginationData,
        string $sortfield,
        string $sortorder,
        int $limit,
        int $page,
        string $sqlfilters
    ): JsonResponse {
        // case of external user, $thirdparty_ids param is ignored and replaced by user's socid
        $socids = $this->config->apiSocId() > 0 ? (string) $this->config->apiSocId() : $thirdpartyIds;

        // If the internal user must only see his customers, force searching by him
        $searchSale = 0;
        if (!$this->config->hasRight('societe', 'client', 'voir') && !$socids) {
            $searchSale = $this->config->apiUserId();
        }

        $sql = 'SELECT t.rowid';
        $sql .= ' FROM llx_socpeople as t';
        $sql .= ' LEFT JOIN llx_socpeople_extrafields as te ON te.fk_object = t.rowid';
        $sql .= ' LEFT JOIN llx_societe as s ON t.fk_soc = s.rowid';
        $sql .= ' WHERE t.entity IN (' . $this->config->getEntity('contact') . ')';
        if ($socids !== '') {
            $sql .= ' AND t.fk_soc IN (' . $this->sanitizeList($socids) . ')';
        }
        // Search on sale representative
        if ($searchSale && $searchSale != -1) {
            if ($searchSale == -2) {
                $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user IS NULL)';
            } elseif ($searchSale > 0) {
                $sql .= ' AND EXISTS (SELECT sc.fk_soc FROM llx_societe_commerciaux as sc WHERE sc.fk_soc = t.fk_soc AND sc.fk_user = ' . (int) $searchSale . ')';
            }
        }
        // Select contacts of given category. Upstream loops over a list built
        // from $category; inside `if ($category > 0)` only the EXISTS branch
        // can trigger, so the generated SQL is the same.
        if ($category > 0) {
            $sql .= ' AND ( EXISTS (SELECT ck.fk_socpeople FROM llx_categorie_contact as ck WHERE t.rowid = ck.fk_socpeople AND ck.fk_categorie = ' . ((int) $category) . ') )';
        }
        // Add sql filters
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
        } catch (\Throwable) {
            throw new ApiErrorException(503, 'Error when retrieve contacts : ' . $sql);
        }

        $objRet = [];
        $num = count($rows);
        $min = min($num, ($limit <= 0 ? $num : $limit));
        for ($i = 0; $i < $min; $i++) {
            $contact = new Contact();
            if ($this->contactService->fetch($contact, (int) $rows[$i]['rowid']) > 0) {
                $this->contactService->fetchRoles($contact);
                if ($includecount) {
                    $this->contactService->loadRefElements($contact);
                }
                if ($includeroles) {
                    $this->contactService->fetchRoles($contact);
                }
                if ($this->config->isModEnabled('mailing')) {
                    $this->contactService->getNoEmail($contact);
                }

                $data = $this->serializer->toArray($contact);
                if ($properties !== '') {
                    $data = $this->serializer->filterProperties($data, $properties);
                }
                $objRet[] = $data;
            }
        }

        if ($paginationData) {
            $total = (int) ($this->db->fetchOne($sqlTotals) ?: 0);
            $objRet = [
                'data' => $objRet,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'page_count' => $limit ? (int) ceil($total / $limit) : 0,
                    'limit' => $limit,
                ],
            ];
        }

        return new JsonResponse($objRet);
    }

    // ==================================================================
    //  GET /api/contacts/{id} — get() / GET email/{email} — getByEmail()
    // ==================================================================

    #[Route('/api/contacts/{id}', name: 'contacts_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id, Request $request): JsonResponse
    {
        $includecount = (int) ($request->query->get('includecount') ?? 0);
        $includeroles = (int) ($request->query->get('includeroles') ?? 0);

        return $this->doGet($id, '', $includecount, $includeroles);
    }

    #[Route('/api/contacts/email/{email}', name: 'contacts_get_by_email', methods: ['GET'])]
    public function getByEmail(string $email, Request $request): JsonResponse
    {
        $includecount = (int) ($request->query->get('includecount') ?? 0);
        $includeroles = (int) ($request->query->get('includeroles') ?? 0);

        return $this->doGet(0, $email, $includecount, $includeroles);
    }

    private function doGet(int $id, string $email, int $includecount, int $includeroles): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'lire', 'No permission to read contacts');

        $contact = new Contact();
        if ($id === 0 && $email === '') {
            $result = $this->contactService->initAsSpecimen($contact);
        } else {
            $result = $this->contactService->fetch($contact, $id, '', $email);
        }

        if (!$result) {
            throw new ApiErrorException(404, 'Contact not found');
        }

        if (!$this->contactService->checkAccessToResource('contact', (int) $contact->id, 'socpeople&societe')) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }

        if ($includecount) {
            $this->contactService->loadRefElements($contact);
        }
        if ($includeroles) {
            $this->contactService->fetchRoles($contact);
        }
        if ($this->config->isModEnabled('mailing')) {
            $this->contactService->getNoEmail($contact);
        }

        return new JsonResponse($this->serializer->toArray($contact));
    }

    // ==================================================================
    //  POST /api/contacts — post()
    // ==================================================================

    #[Route('/api/contacts', name: 'contacts_post', methods: ['POST'])]
    public function post(Request $request): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'creer', 'No permission to create/update contacts');

        $requestData = $this->body($request);

        // Check mandatory fields (Contacts::$FIELDS)
        foreach (self::MANDATORY_FIELDS as $field) {
            if (!isset($requestData[$field])) {
                throw new ApiErrorException(400, "$field field missing");
            }
        }

        // External api user does not know internal country ID
        if (!isset($requestData['country_id']) && isset($requestData['country_code'])) {
            $field = strlen((string) $requestData['country_code']) > 2 ? 'code_iso' : 'code';
            $id = $this->utils->getIdFromCode((string) $requestData['country_code'], 'c_country', $field, 'rowid');
            if (is_numeric($id) && (int) $id < 0) {
                throw new ApiErrorException(404, 'Country code not found in database');
            }
            $requestData['country_id'] = $id;
        }

        $contact = new Contact();
        $this->applyRequestData($contact, $requestData);

        if ($this->contactService->create($contact) < 0) {
            throw new ApiErrorException(500, 'Error creating contact', array_merge([$this->contactService->error], $this->contactService->errors));
        }
        if ($this->config->isModEnabled('mailing') && !empty($contact->email) && isset($contact->no_email)) {
            $this->contactService->setNoEmail($contact, (int) $contact->no_email);
        }

        return new JsonResponse($contact->id);
    }

    // ==================================================================
    //  PUT /api/contacts/{id} — put()
    // ==================================================================

    #[Route('/api/contacts/{id}', name: 'contacts_put', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function put(int $id, Request $request): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'creer', 'No permission to create/update contacts');

        $contact = new Contact();
        if ($this->contactService->fetch($contact, $id) <= 0) {
            throw new ApiErrorException(404, 'Contact not found');
        }
        if (!$this->contactService->checkAccessToResource('contact', (int) $contact->id, 'socpeople&societe')) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }

        $this->applyRequestData($contact, $this->body($request), true);

        if ($this->config->isModEnabled('mailing') && !empty($contact->email) && isset($contact->no_email)) {
            $this->contactService->setNoEmail($contact, (int) $contact->no_email);
        }

        if ($this->contactService->update($contact, $id, 0, 'update') > 0) {
            return $this->doGet($id, '', 0, 0);
        }

        throw new ApiErrorException(500, $this->contactService->error !== '' ? $this->contactService->error : 'Error when update contact');
    }

    // ==================================================================
    //  DELETE /api/contacts/{id} — delete()
    // ==================================================================

    #[Route('/api/contacts/{id}', name: 'contacts_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'supprimer', 'No permission to delete contacts');

        $contact = new Contact();
        if ($this->contactService->fetch($contact, $id) <= 0) {
            throw new ApiErrorException(404, 'Contact not found');
        }
        if (!$this->contactService->checkAccessToResource('contact', (int) $contact->id, 'socpeople&societe')) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }

        if ($this->contactService->delete($contact) <= 0) {
            throw new ApiErrorException(500, 'Error when delete contact ' . $this->contactService->error);
        }

        return new JsonResponse(['success' => ['code' => 200, 'message' => 'Contact deleted']]);
    }

    // ==================================================================
    //  categories (GET/PUT/DELETE /api/contacts/{id}/categories[/...])
    // ==================================================================

    #[Route('/api/contacts/{id}/categories', name: 'contacts_get_categories', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getCategories(int $id, Request $request): JsonResponse
    {
        if (!$this->config->hasRight('categorie', 'lire')) {
            throw new ApiErrorException(403);
        }

        $sortfield = (string) ($request->query->get('sortfield') ?? 's.rowid');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 0);
        $page = (int) ($request->query->get('page') ?? 0);

        $result = $this->categoryService->getListForItem($id, $sortfield, $sortorder, $limit, $page);
        if ($result === -1) {
            throw new ApiErrorException(503, 'Error when retrieve category list : ' . $this->categoryService->getLastError());
        }

        return new JsonResponse($result);
    }

    #[Route('/api/contacts/{id}/categories/{category_id}', name: 'contacts_add_category', requirements: ['id' => '\d+', 'category_id' => '\d+'], methods: ['PUT'])]
    public function addCategory(int $id, int $category_id): JsonResponse
    {
        return $this->linkCategory($id, $category_id, 'add');
    }

    #[Route('/api/contacts/{id}/categories/{category_id}', name: 'contacts_del_category', requirements: ['id' => '\d+', 'category_id' => '\d+'], methods: ['DELETE'])]
    public function deleteCategory(int $id, int $category_id): JsonResponse
    {
        return $this->linkCategory($id, $category_id, 'del');
    }

    private function linkCategory(int $id, int $categoryId, string $op): JsonResponse
    {
        $this->requireRight('societe', 'contact', 'creer', 'Insufficient rights');

        $contact = new Contact();
        if ($this->contactService->fetch($contact, $id) <= 0) {
            throw new ApiErrorException(404, 'Contact not found');
        }
        $category = $this->categoryService->fetch($categoryId);
        if ($category === null) {
            throw new ApiErrorException(404, 'category not found');
        }

        if (!$this->contactService->checkAccessToResource('contact', (int) $contact->id)) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }
        if (!$this->contactService->checkAccessToResource('category', $categoryId)) {
            throw new ApiErrorException(403, 'Access not allowed for login ' . $this->config->apiUserLogin());
        }

        if ($op === 'add') {
            $this->categoryService->addType($categoryId, (int) $contact->id);
        } else {
            $this->categoryService->delType($categoryId, (int) $contact->id);
        }

        return new JsonResponse($this->serializer->toArray($contact));
    }

    // ==================================================================
    //  helpers
    // ==================================================================

    /**
     * Assign request fields onto the Contact like the upstream API does
     * (caller → context, array_options → extrafields, socid → thirdparty
     * existence + access check, everything else → _checkValForAPI).
     *
     * @param array<string, mixed> $requestData
     */
    private function applyRequestData(Contact $contact, array $requestData, bool $skipId = false): void
    {
        foreach ($requestData as $field => $value) {
            if ($skipId && $field === 'id') {
                continue;
            }
            if ($field === 'caller') {
                // mention of caller on trigger context
                $contact->context['caller'] = $this->utils->sanitizeVal($requestData['caller'], 'aZ09');
                continue;
            }
            if ($field === 'array_options' && is_array($value)) {
                foreach ($value as $index => $val) {
                    $contact->array_options[$index] = $this->checkValExtrafieldsForAPI((string) $index, $val);
                }
                continue;
            }
            if ($field === 'socid') {
                $newSocid = (int) $value;
                $thirdpartyExists = $this->db->fetchOne('SELECT rowid FROM llx_societe WHERE rowid = ' . $newSocid);
                if ($thirdpartyExists === false) {
                    throw new ApiErrorException(404, 'Thirdparty with id=' . $newSocid . ' not found or not allowed');
                }
                if (!$this->contactService->checkAccessToResource('societe', $newSocid)) {
                    throw new ApiErrorException(403, 'Access to socid/thirdparty=' . $newSocid . ' is not allowed for login ' . $this->config->apiUserLogin());
                }
            }

            $contact->$field = $this->checkValForAPI($field, $value);
        }
    }

    /**
     * Port of DolibarrApi::_checkValForAPI() for the Contact object.
     */
    private function checkValForAPI(string $field, mixed $value): mixed
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            throw new ApiErrorException(400, 'Parameter ' . $field . ' is not allowed in request');
        }

        if (!is_array($value)) {
            // forbidden properties
            if (
                in_array($field, [
                'db', 'table_element', 'table_rowid', 'table_ref_field', 'table_element_line', 'element', 'fk_element', 'element_for_permission', 'class_element_line',
                'fields', 'TRIGGER_PREFIX', 'picto',
                'restrictiononfksoc', 'ismultientitymanaged', 'isextrafieldmanaged',
                'module', 'error', 'errorhidden', 'errors', 'warning', 'warnings', 'validateFieldsErrors',
                'oldcopy', 'oldref', 'newref', 'context',
                'actionmsg', 'actionmsg2', 'thirdparty', 'user',
                'tpl', 'extraparams',
                'childtables', 'childtablesoncascade',
                ], true)
            ) {
                throw new ApiErrorException(400, 'Parameter ' . $field . ' is not allowed in request');
            }

            // Sanitize the value using its type declared into Contact::$fields
            $type = self::fieldType($field);
            if ($type !== '') {
                if (str_starts_with($type, 'int') || str_starts_with($type, 'double') || in_array($type, ['real', 'price', 'stock'], true)) {
                    return $this->utils->sanitizeVal($value, 'int');
                }
                if ($type === 'html') {
                    return $this->utils->sanitizeVal($value, 'restricthtml');
                }
                if (in_array($type, ['select', 'sellist', 'checkbox', 'boolean', 'radio'], true)) {
                    return $this->utils->sanitizeVal($value, 'alphanohtml');
                }
                if ($type === 'email') {
                    return $this->utils->sanitizeVal($value, 'email');
                }
                if ($type === 'password') {
                    return $this->utils->sanitizeVal($value, 'password');
                }
                // Others will use 'alphanohtml'
            }

            // field name heuristics
            if (preg_match('/^fk_/i', $field)) {
                return $this->utils->sanitizeVal($value, 'int');
            }
            if (in_array($field, ['note', 'note_private', 'note_public', 'desc', 'description'], true)) {
                return $this->utils->sanitizeVal($value, 'restricthtml');
            }

            return $this->utils->sanitizeVal($value, 'alphanohtml');
        }

        // array value: recurse per element
        $newarrayvalue = [];
        foreach ($value as $tmpkey => $tmpvalue) {
            $newarrayvalue[$tmpkey] = $this->checkValForAPI((string) $tmpkey, $tmpvalue);
        }

        return $newarrayvalue;
    }

    /**
     * Port of DolibarrApi::_checkValExtrafieldsForAPI(). This service has no
     * extrafields definition table loaded, so every value falls back to the
     * alphanohtml mode (same result as upstream when no definition matches).
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

    private function requireRight(string $module, string $permLevel1, string $permLevel2, string $message): void
    {
        if (!$this->config->hasRight($module, $permLevel1, $permLevel2)) {
            throw new ApiErrorException(403, $message);
        }
    }

    /** Port of $db->sanitize($list, 1): int-only comma list. */
    private function sanitizeList(string $list): string
    {
        return implode(',', array_map('intval', array_filter(explode(',', $list), static fn ($v) => $v !== '')));
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
                $return .= ' ' . ($oldsortorder !== '' ? $oldsortorder : 'ASC');
            }
            $i++;
        }

        return $return;
    }
}
