<?php

declare(strict_types=1);

namespace App\Contact;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Port of the contact dictionaries of htdocs/api/class/api_setup.class.php:
 * GET /setup/dictionary/contact_types (llx_c_type_contact) and
 * GET /setup/dictionary/civilities (llx_c_civility).
 *
 * No translation layer exists in this service: upstream translateLabel()
 * falls back to the raw libelle, so the DB label is returned.
 */
final class ContactSetupController extends AbstractController
{
    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
    ) {
    }

    #[Route('/api/setup/dictionary/contact_types', name: 'dictionary_contact_types', methods: ['GET'])]
    public function contactTypes(Request $request): JsonResponse
    {
        $sortfield = (string) ($request->query->get('sortfield') ?? 'code');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 100);
        $page = (int) ($request->query->get('page') ?? 0);
        $type = (string) ($request->query->get('type') ?? '');
        $module = (string) ($request->query->get('module') ?? '');
        $active = (int) ($request->query->get('active') ?? 1);
        $sqlfilters = (string) ($request->query->get('sqlfilters') ?? '');

        if ($type === 'expedition' && !$this->config->getInt('SHIPPING_USE_ITS_OWN_CONTACTS')) {
            $type = 'commande';
        }

        $sql = 'SELECT rowid, code, element as type, libelle as label, source, module, position';
        $sql .= ' FROM llx_c_type_contact as t';
        $sql .= ' WHERE t.active = ' . ((int) $active);
        if ($type !== '') {
            $sql .= " AND t.element LIKE '%" . $this->escapeLike($type) . "%'";
        }
        if ($module !== '') {
            $sql .= " AND t.module LIKE '%" . $this->escapeLike($module) . "%'";
        }
        if ($sqlfilters !== '') {
            $errormessage = '';
            $sql .= (new UniversalSearchFilter($this->db))->forge($sqlfilters, $errormessage);
            if ($errormessage !== '') {
                throw new ApiErrorException(400, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
        }

        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . ($limit * $page);
        }

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            throw new ApiErrorException(503, 'Error when retrieving list of contacts types : ' . $e->getMessage());
        }

        return new JsonResponse($rows);
    }

    #[Route('/api/setup/dictionary/civilities', name: 'dictionary_civilities', methods: ['GET'])]
    public function civilities(Request $request): JsonResponse
    {
        $sortfield = (string) ($request->query->get('sortfield') ?? 'code');
        $sortorder = (string) ($request->query->get('sortorder') ?? 'ASC');
        $limit = (int) ($request->query->get('limit') ?? 100);
        $page = (int) ($request->query->get('page') ?? 0);
        $module = (string) ($request->query->get('module') ?? '');
        $active = (int) ($request->query->get('active') ?? 1);
        $sqlfilters = (string) ($request->query->get('sqlfilters') ?? '');

        $sql = 'SELECT rowid, code, label, module';
        $sql .= ' FROM llx_c_civility as t';
        $sql .= ' WHERE t.active = ' . ((int) $active);
        if ($module !== '') {
            $sql .= " AND t.module LIKE '%" . $this->escapeLike($module) . "%'";
        }
        if ($sqlfilters !== '') {
            $errormessage = '';
            $sql .= (new UniversalSearchFilter($this->db))->forge($sqlfilters, $errormessage);
            if ($errormessage !== '') {
                throw new ApiErrorException(400, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
        }

        $sql .= $this->orderBy($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . ($limit * $page);
        }

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            throw new ApiErrorException(503, 'Error when retrieving list of civility : ' . $e->getMessage());
        }

        return new JsonResponse($rows);
    }

    /** Port of $db->escape() — escapes SQL metachars, not LIKE wildcards. */
    private function escapeLike(string $value): string
    {
        return addslashes($value);
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
