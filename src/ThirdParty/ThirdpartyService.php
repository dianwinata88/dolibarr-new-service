<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of Societe (htdocs/societe/class/societe.class.php) for the API slice.
 *
 * Mirrors upstream behavior with raw SQL against llx_societe and related
 * tables: fetch, verify, create, update, delete, merge, code checks,
 * categories, sales representatives, discounts, outstanding helpers.
 *
 * Return codes replicate upstream: positive on success, 0 / negative on
 * failure, with $this->error / $this->errors filled.
 */
final class ThirdpartyService
{
    public ?string $error = null;
    /** @var string[] */
    public array $errors = [];

    private const ERROR_MAP_CLIENT = [
        -1 => 'ErrorBadCustomerCodeSyntax',
        -2 => 'ErrorCustomerCodeRequired',
        -3 => 'ErrorCustomerCodeAlreadyUsed',
        -4 => 'ErrorPrefixRequired',
    ];
    private const ERROR_MAP_SUPPLIER = [
        -1 => 'ErrorBadSupplierCodeSyntax',
        -2 => 'ErrorSupplierCodeRequired',
        -3 => 'ErrorSupplierCodeAlreadyUsed',
        -4 => 'ErrorPrefixRequired',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
        private readonly DolibarrUtils $utils,
        private readonly NumberingService $numbering,
        private readonly ProfidValidator $profids,
    ) {
    }

    // ==================================================================
    //  fetch() — port of Societe::fetch + fetch_optionals
    // ==================================================================

    /**
     * Returns <0 error, 0 not found, >0 id.
     *
     * @return int
     */
    public function fetch(Company $s, int $rowid = 0, string $ref = '', string $ref_ext = '', string $barcode = '', string $idprof1 = '', string $idprof2 = '', string $idprof3 = '', string $idprof4 = '', string $idprof5 = '', string $idprof6 = '', string $email = '', string $ref_alias = ''): int
    {
        if (
            empty($rowid) && empty($ref) && empty($ref_ext) && empty($barcode)
            && empty($idprof1) && empty($idprof2) && empty($idprof3)
            && empty($idprof4) && empty($idprof5) && empty($idprof6)
            && empty($email) && empty($ref_alias)
        ) {
            return -1;
        }

        $sql = 'SELECT s.rowid, s.nom as name, s.name_alias, s.entity, s.ref_ext, s.address, s.datec as date_creation, s.prefix_comm';
        $sql .= ', s.status, s.fk_warehouse, s.price_level';
        $sql .= ', s.tms as date_modification, s.fk_user_creat, s.fk_user_modif';
        $sql .= ', s.phone, s.phone_mobile, s.fax, s.email, s.socialnetworks';
        $sql .= ', s.url, s.zip, s.town, s.note_private, s.note_public, s.client, s.fournisseur';
        $sql .= ', s.siren as idprof1, s.siret as idprof2, s.ape as idprof3, s.idprof4, s.idprof5, s.idprof6';
        $sql .= ', s.capital, s.tva_intra, s.euid';
        $sql .= ', s.fk_typent as typent_id, s.fk_effectif as effectif_id';
        $sql .= ', s.fk_forme_juridique as forme_juridique_code, s.birth';
        $sql .= ', s.webservices_url, s.webservices_key, s.model_pdf, s.last_main_doc';
        $sql .= ', s.accountancy_code_customer_general, s.code_compta';
        $sql .= ', s.accountancy_code_supplier_general, s.code_compta_fournisseur';
        $sql .= ', s.accountancy_code_buy, s.accountancy_code_sell';
        $sql .= ', s.vat_reverse_charge as soc_vat_reverse_charge';
        $sql .= ', s.mode_reglement, s.cond_reglement, s.fk_account';
        $sql .= ', s.mode_reglement_supplier, s.cond_reglement_supplier';
        $sql .= ', s.code_client, s.code_fournisseur, s.parent, s.barcode';
        $sql .= ', s.fk_departement as state_id, s.fk_pays as country_id, s.fk_stcomm, s.deposit_percent, s.transport_mode';
        $sql .= ', s.tva_assuj, s.transport_mode_supplier';
        $sql .= ', s.localtax1_assuj, s.localtax1_value, s.localtax2_assuj, s.localtax2_value, s.fk_prospectlevel, s.default_lang, s.logo, s.logo_squarred';
        $sql .= ', s.fk_shipping_method, s.outstanding_limit, s.import_key, s.canvas, s.fk_incoterms, s.location_incoterms';
        $sql .= ', s.order_min_amount, s.supplier_order_min_amount';
        $sql .= ', s.fk_multicurrency, s.multicurrency_code';
        $sql .= ', s.remise_client, s.remise_supplier';
        $sql .= ', fj.libelle as forme_juridique';
        $sql .= ', e.libelle as effectif';
        $sql .= ', c.code as country_code, c.label as country';
        $sql .= ', d.code_departement as state_code, d.nom as state';
        $sql .= ', r.rowid as region_id, r.code_region as region_code';
        $sql .= ', st.libelle as stcomm, st.picto as stcomm_picto';
        $sql .= ', te.code as typent_code';
        $sql .= ', i.libelle as label_incoterms';
        $sql .= ' FROM llx_societe as s';
        $sql .= ' LEFT JOIN llx_societe_extrafields as sef ON sef.fk_object=s.rowid';
        $sql .= ' LEFT JOIN llx_c_effectif as e ON s.fk_effectif = e.id';
        $sql .= ' LEFT JOIN llx_c_country as c ON s.fk_pays = c.rowid';
        $sql .= ' LEFT JOIN llx_c_stcomm as st ON s.fk_stcomm = st.id';
        $sql .= ' LEFT JOIN llx_c_forme_juridique as fj ON s.fk_forme_juridique = fj.code';
        $sql .= ' LEFT JOIN llx_c_departements as d ON s.fk_departement = d.rowid';
        $sql .= ' LEFT JOIN llx_c_regions as r ON d.fk_region = r.code_region';
        $sql .= ' LEFT JOIN llx_c_typent as te ON s.fk_typent = te.id';
        $sql .= ' LEFT JOIN llx_c_incoterms as i ON s.fk_incoterms = i.rowid';
        $sql .= ' WHERE s.entity IN (' . $this->config->getEntity('societe') . ')';

        if ($rowid) {
            $sql .= ' AND s.rowid = ' . ((int) $rowid);
        }
        foreach (
            [
            'ref' => 'nom', 'ref_alias' => 'name_alias', 'ref_ext' => 'ref_ext',
            'barcode' => 'barcode', 'idprof1' => 'siren', 'idprof2' => 'siret',
            'idprof3' => 'ape', 'idprof4' => 'idprof4', 'idprof5' => 'idprof5',
            'idprof6' => 'idprof6', 'email' => 'email',
            ] as $param => $col
        ) {
            if (!empty($$param)) {
                $sql .= " AND s.$col = " . $this->db->quote((string) $$param);
            }
        }

        $rows = $this->db->fetchAllAssociative($sql);
        $num = count($rows);
        if ($num > 1) {
            $this->error = 'Fetch found several records. Rename one of thirdparties to avoid duplicate.';

            return -2;
        }
        if ($num === 0) {
            return 0;
        }

        $obj = $rows[0];

        $s->id = (int) $obj['rowid'];
        $s->entity = (int) $obj['entity'];
        $s->canvas = $obj['canvas'];

        $s->ref = $obj['name'];
        $s->name = $obj['name'];
        $s->nom = $obj['name'];
        $s->name_alias = $obj['name_alias'];
        $s->ref_ext = $obj['ref_ext'];

        $s->date_creation = $this->jdate($obj['date_creation']);
        $s->date_modification = $this->jdate($obj['date_modification']);
        $s->user_creation_id = $obj['fk_user_creat'] === null ? null : (int) $obj['fk_user_creat'];
        $s->user_modification_id = $obj['fk_user_modif'] === null ? null : (int) $obj['fk_user_modif'];

        $s->address = $obj['address'];
        $s->zip = $obj['zip'];
        $s->town = $obj['town'];

        $s->country_id = $obj['country_id'] === null ? null : (int) $obj['country_id'];
        $s->country_code = $obj['country_id'] ? $obj['country_code'] : '';
        $s->country = $obj['country_id'] ? $obj['country'] : '';

        $s->state_id = $obj['state_id'] === null ? null : (int) $obj['state_id'];
        $s->state_code = $obj['state_code'];
        $s->region_id = $obj['region_id'] === null ? null : (int) $obj['region_id'];
        $s->region_code = $obj['region_code'] === null ? null : (int) $obj['region_code'];
        $s->state = ($obj['state'] !== '-' ? $obj['state'] : '');

        // No translation layer: upstream uses $langs->trans('StatusProspect'.$obj->fk_stcomm)
        // which falls back to the raw libelle when no translation is loaded.
        $s->stcomm_id = $obj['fk_stcomm'] === null ? null : (int) $obj['fk_stcomm'];
        $s->status_prospect_label = $obj['stcomm'];
        $s->stcomm_picto = $obj['stcomm_picto'];

        $s->email = $obj['email'];
        $s->socialnetworks = $obj['socialnetworks'] ? (array) json_decode((string) $obj['socialnetworks'], true) : [];

        $s->url = $obj['url'];
        $s->phone = $obj['phone'];
        $s->phone_mobile = $obj['phone_mobile'];
        $s->fax = $obj['fax'];

        $s->parent = $obj['parent'] === null ? null : (int) $obj['parent'];

        $s->idprof1 = $obj['idprof1'];
        $s->idprof2 = $obj['idprof2'];
        $s->idprof3 = $obj['idprof3'];
        $s->idprof4 = $obj['idprof4'];
        $s->idprof5 = $obj['idprof5'];
        $s->idprof6 = $obj['idprof6'];

        $s->capital = $obj['capital'] === null ? null : (float) $obj['capital'];

        $s->code_client = $obj['code_client'];
        $s->code_fournisseur = $obj['code_fournisseur'];

        $s->accountancy_code_customer_general = $obj['accountancy_code_customer_general'];
        $s->code_compta_client = $obj['code_compta'];
        $s->accountancy_code_supplier_general = $obj['accountancy_code_supplier_general'];
        $s->code_compta_fournisseur = $obj['code_compta_fournisseur'];

        $s->barcode = $obj['barcode'];

        $s->tva_assuj = (int) $obj['tva_assuj'];
        $s->tva_intra = $obj['tva_intra'];
        $s->vat_reverse_charge = !empty($obj['soc_vat_reverse_charge']) ? (int) $obj['soc_vat_reverse_charge'] : 0;
        $s->euid = $obj['euid'];

        $s->status = (int) $obj['status'];

        $s->localtax1_assuj = $obj['localtax1_assuj'] === null ? null : (int) $obj['localtax1_assuj'];
        $s->localtax2_assuj = $obj['localtax2_assuj'] === null ? null : (int) $obj['localtax2_assuj'];
        $s->localtax1_value = $obj['localtax1_value'] === null ? null : (float) $obj['localtax1_value'];
        $s->localtax2_value = $obj['localtax2_value'] === null ? null : (float) $obj['localtax2_value'];

        $s->typent_id = $obj['typent_id'] === null ? 0 : (int) $obj['typent_id'];
        $s->typent_code = $obj['typent_code'];

        $s->effectif_id = $obj['effectif_id'] === null ? 0 : (int) $obj['effectif_id'];
        $s->effectif = $s->effectif_id ? $obj['effectif'] : '';

        $s->forme_juridique_code = $obj['forme_juridique_code'] === null ? 0 : (int) $obj['forme_juridique_code'];
        $s->forme_juridique = $s->forme_juridique_code ? $obj['forme_juridique'] : '';
        $s->birth = $this->jdate($obj['birth']);

        $s->fk_prospectlevel = $obj['fk_prospectlevel'];

        $s->prefix_comm = $obj['prefix_comm'];

        $s->remise_percent = $obj['remise_client'] ? $this->utils->price2num($obj['remise_client']) : 0;
        $s->remise_supplier_percent = $obj['remise_supplier'];

        $s->mode_reglement_id = $obj['mode_reglement'] === null ? null : (int) $obj['mode_reglement'];
        $s->cond_reglement_id = $obj['cond_reglement'] === null ? null : (int) $obj['cond_reglement'];
        $s->deposit_percent = $obj['deposit_percent'];
        $s->transport_mode_id = $obj['transport_mode'] === null ? null : (int) $obj['transport_mode'];
        $s->mode_reglement_supplier_id = $obj['mode_reglement_supplier'] === null ? null : (int) $obj['mode_reglement_supplier'];
        $s->cond_reglement_supplier_id = $obj['cond_reglement_supplier'] === null ? null : (int) $obj['cond_reglement_supplier'];
        $s->transport_mode_supplier_id = $obj['transport_mode_supplier'] === null ? null : (int) $obj['transport_mode_supplier'];
        $s->shipping_method_id = ($obj['fk_shipping_method'] ?? 0) > 0 ? (int) $obj['fk_shipping_method'] : null;
        $s->fk_account = $obj['fk_account'] === null ? null : (int) $obj['fk_account'];

        $s->client = (int) $obj['client'];
        $s->fournisseur = (int) $obj['fournisseur'];

        $s->note = $obj['note_private'];
        $s->note_private = $obj['note_private'];
        $s->note_public = $obj['note_public'];
        $s->model_pdf = $obj['model_pdf'];
        $s->default_lang = $obj['default_lang'];
        $s->logo = $obj['logo'];
        $s->logo_squarred = $obj['logo_squarred'];

        $s->webservices_url = $obj['webservices_url'];
        $s->webservices_key = $obj['webservices_key'];

        $s->accountancy_code_buy = $obj['accountancy_code_buy'];
        $s->accountancy_code_sell = $obj['accountancy_code_sell'];

        $s->outstanding_limit = $obj['outstanding_limit'];
        $s->order_min_amount = $obj['order_min_amount'];
        $s->supplier_order_min_amount = $obj['supplier_order_min_amount'];

        $s->price_level = $obj['price_level'] === null ? null : (int) $obj['price_level'];

        $s->fk_warehouse = $obj['fk_warehouse'] === null ? null : (int) $obj['fk_warehouse'];

        $s->import_key = $obj['import_key'];

        $s->fk_incoterms = $obj['fk_incoterms'] === null ? null : (int) $obj['fk_incoterms'];
        $s->location_incoterms = $obj['location_incoterms'];
        $s->label_incoterms = $obj['label_incoterms'];

        $s->fk_multicurrency = $obj['fk_multicurrency'] === null ? null : (int) $obj['fk_multicurrency'];
        $s->multicurrency_code = $obj['multicurrency_code'];

        $s->last_main_doc = $obj['last_main_doc'];

        // fetch_optionals: extra fields
        $s->array_options = $this->fetchExtrafields('societe', $s->id);

        // price_level defaults to 1 when multiprices are enabled
        if (($this->config->getString('PRODUIT_MULTIPRICES') || $this->config->getString('PRODUIT_CUSTOMER_PRICES_BY_QTY_MULTIPRICES') || $this->config->getString('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')) && empty($s->price_level)) {
            $s->price_level = 1;
        }

        return $s->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchExtrafields(string $tableElement, int $fkObject): array
    {
        $table = 'llx_' . $tableElement . '_extrafields';
        if (!$this->tableExists($table)) {
            return [];
        }
        $row = $this->db->fetchAssociative("SELECT * FROM $table WHERE fk_object = ?", [$fkObject]);
        if ($row === false) {
            return [];
        }
        unset($row['rowid'], $row['tms'], $row['fk_object'], $row['import_key']);

        // upstream stores them as array_options['options_xxx']
        $options = [];
        foreach ($row as $key => $value) {
            $options['options_' . $key] = $value;
        }

        return $options;
    }

    // ==================================================================
    //  verify() — port of Societe::verify
    // ==================================================================

    /**
     * @return int >=0 OK, <0 KO ($this->errors filled)
     */
    public function verify(Company $s): int
    {
        $this->error = '';
        $this->errors = [];

        $s->name = trim((string) ($s->name ?? $s->nom ?? ''));

        // Check for thirdparty creation on mandatory fields
        if (empty($s->name) || $s->name == '-') {
            $this->errors[] = 'ErrorBadThirdPartyName';
            $result = -2;
        } else {
            $result = 0;
        }

        if ($result >= 0) {
            $this->error = '';
            $this->errors = [];

            if ($s->client) {
                $rescode = $this->checkCodeClient($s);
                $errname = match ($rescode) {
                    -1 => 'ErrorBadCustomerCodeSyntax',
                    -2 => 'ErrorCustomerCodeRequired',
                    -3 => 'ErrorCustomerCodeAlreadyUsed',
                    -4 => 'ErrorPrefixRequired',
                    default => $rescode == -5 ? null : 'ErrorUnknownOnCustomerCodeCheck',
                };
                if ($rescode < 0 && $errname !== null) {
                    $this->errors[] = $errname;
                    $result = -3;
                }
            }

            if ($s->fournisseur) {
                $rescode = $this->checkCodeFournisseur($s);
                $errname = match ($rescode) {
                    -1 => 'ErrorBadSupplierCodeSyntax',
                    -2 => 'ErrorSupplierCodeRequired',
                    -3 => 'ErrorSupplierCodeAlreadyUsed',
                    -4 => 'ErrorPrefixRequired',
                    default => $rescode == -5 ? null : 'ErrorUnknownOnSupplierCodeCheck',
                };
                if ($rescode < 0 && $errname !== null) {
                    $this->errors[] = $errname;
                    $result = -3;
                }
            }

            // Check for duplicate or mandatory prof id
            [, $mysocCountryCode] = $this->config->mysocCountry();
            $mysocCountryId = $this->config->mysocCountry()[0];
            $arrayforcodecfield = [
                'IDPROF1' => ['SOCIETE_IDPROF1_MANDATORY', 'SOCIETE_IDPROF1_UNIQUE', 'idprof1'],
                'IDPROF2' => ['SOCIETE_IDPROF2_MANDATORY', 'SOCIETE_IDPROF2_UNIQUE', 'idprof2'],
                'IDPROF3' => ['SOCIETE_IDPROF3_MANDATORY', 'SOCIETE_IDPROF3_UNIQUE', 'idprof3'],
                'IDPROF4' => ['SOCIETE_IDPROF4_MANDATORY', 'SOCIETE_IDPROF4_UNIQUE', 'idprof4'],
                'IDPROF5' => ['SOCIETE_IDPROF5_MANDATORY', 'SOCIETE_IDPROF5_UNIQUE', 'idprof5'],
                'IDPROF6' => ['SOCIETE_IDPROF6_MANDATORY', 'SOCIETE_IDPROF6_UNIQUE', 'idprof6'],
                'EMAIL' => ['SOCIETE_EMAIL_MANDATORY', 'SOCIETE_EMAIL_UNIQUE', 'email'],
                'TVA_INTRA' => ['SOCIETE_VAT_INTRA_MANDATORY', 'SOCIETE_VAT_INTRA_UNIQUE', 'tva_intra'],
                'EUID' => ['SOCIETE_EUID_MANDATORY', 'SOCIETE_EUID_UNIQUE', 'euid'],
                'ACCOUNTANCY_CODE_CUSTOMER' => ['SOCIETE_ACCOUNTANCY_CODE_CUSTOMER_MANDATORY', 'SOCIETE_ACCOUNTANCY_CODE_CUSTOMER_UNIQUE', 'code_compta_client'],
                'ACCOUNTANCY_CODE_SUPPLIER' => ['SOCIETE_ACCOUNTANCY_CODE_SUPPLIER_MANDATORY', 'SOCIETE_ACCOUNTANCY_CODE_SUPPLIER_UNIQUE', 'code_compta_fournisseur'],
            ];

            $i = 0;
            foreach ($arrayforcodecfield as $key => $confpair) {
                [$mandatoryConst, $uniqueConst, $prop] = $confpair;
                $i++;

                $keymin = strtolower($key);
                if ($i <= 6) {
                    $keymin = 'idprof' . $i;
                }

                $vallabel = (string) ($s->$prop ?? '');

                if ($i > 0 && $i <= 6) {
                    // profid
                    if (
                        empty($vallabel) && $this->isACompany($s) && $mysocCountryId > 0
                        && (int) ($s->country_id ?? 0) === $mysocCountryId
                        && $this->config->getString($mandatoryConst)
                    ) {
                        $this->error = 'Error ' . $key . ' is mandatory but empty';
                        $this->errors[] = $this->error;
                        $result = -4;
                    }

                    // unique check
                    if (
                        $vallabel !== '' && $this->idProfVerifiable($i)
                        && $this->config->getString($uniqueConst)
                        && $this->idProfExists($keymin, $vallabel, $s->id ?? 0)
                    ) {
                        $this->error = 'Error ' . $key . ' already exists';
                        $this->errors[] = $this->error;
                        $result = -4;
                    }
                } elseif ($key === 'EMAIL') {
                    if (empty($vallabel) && $this->config->getString($mandatoryConst)) {
                        $this->error = 'Error ' . $key . ' is mandatory but empty';
                        $this->errors[] = $this->error;
                        $result = -4;
                    } elseif ($vallabel !== '' && !$this->utils->isValidEmail($vallabel)) {
                        $this->error = 'Error ' . $key . ' is not valid';
                        $this->errors[] = $this->error;
                        $result = -4;
                    } elseif (
                        $vallabel !== '' && $this->config->getString($uniqueConst)
                        && $this->idProfExists('email', $vallabel, $s->id ?? 0)
                    ) {
                        $this->error = 'Error ' . $key . ' already exists';
                        $this->errors[] = $this->error;
                        $result = -4;
                    }
                } elseif ($key === 'TVA_INTRA') {
                    if (empty($vallabel) && $s->tva_assuj && $this->config->getString($mandatoryConst)) {
                        $this->error = 'Error ' . $key . ' is mandatory but empty';
                        $this->errors[] = $this->error;
                        $result = -4;
                    } elseif (
                        $vallabel !== '' && $this->config->getString($uniqueConst)
                        && $this->idProfExists('tva_intra', $vallabel, $s->id ?? 0)
                    ) {
                        $this->error = 'Error ' . $key . ' already exists';
                        $this->errors[] = $this->error;
                        $result = -4;
                    }
                } elseif ($key === 'EUID') {
                    if (empty($vallabel) && $this->config->getString($mandatoryConst)) {
                        $this->error = 'Error ' . $key . ' is mandatory but empty';
                        $this->errors[] = $this->error;
                        $result = -4;
                    } elseif (
                        $vallabel !== '' && $this->config->getString($uniqueConst)
                        && $this->idProfExists('euid', $vallabel, $s->id ?? 0)
                    ) {
                        $this->error = 'Error ' . $key . ' already exists';
                        $this->errors[] = $this->error;
                        $result = -4;
                    }
                } elseif ($key === 'ACCOUNTANCY_CODE_CUSTOMER') {
                    if ($s->client) {
                        if (empty($vallabel) && $this->config->getString($mandatoryConst)) {
                            $this->error = 'Error ' . $key . ' is mandatory but empty';
                            $this->errors[] = $this->error;
                            $result = -4;
                        } elseif (
                            $vallabel !== '' && $this->config->getString($uniqueConst)
                            && $this->idProfExists('code_compta', $vallabel, $s->id ?? 0)
                        ) {
                            $this->error = 'Error ' . $key . ' already exists';
                            $this->errors[] = $this->error;
                            $result = -4;
                        }
                    }
                } elseif ($key === 'ACCOUNTANCY_CODE_SUPPLIER') {
                    if ($s->fournisseur) {
                        if (empty($vallabel) && $this->config->getString($mandatoryConst)) {
                            $this->error = 'Error ' . $key . ' is mandatory but empty';
                            $this->errors[] = $this->error;
                            $result = -4;
                        } elseif (
                            $vallabel !== '' && $this->config->getString($uniqueConst)
                            && $this->idProfExists('code_compta_fournisseur', $vallabel, $s->id ?? 0)
                        ) {
                            $this->error = 'Error ' . $key . ' already exists';
                            $this->errors[] = $this->error;
                            $result = -4;
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function idProfVerifiable(int $idprof): bool
    {
        return match ($idprof) {
            1 => $this->config->getBool('SOCIETE_IDPROF1_UNIQUE'),
            2 => $this->config->getBool('SOCIETE_IDPROF2_UNIQUE'),
            3 => $this->config->getBool('SOCIETE_IDPROF3_UNIQUE'),
            4 => $this->config->getBool('SOCIETE_IDPROF4_UNIQUE'),
            5 => $this->config->getBool('SOCIETE_IDPROF5_UNIQUE'),
            6 => $this->config->getBool('SOCIETE_IDPROF6_UNIQUE'),
            default => false,
        };
    }

    /**
     * Port of id_prof_exists(): is the value already used by another
     * thirdparty for this field?
     */
    public function idProfExists(string $idprof, string $value, int $socid = 0): bool
    {
        $field = match ($idprof) {
            '1', 'idprof1' => 'siren',
            '2', 'idprof2' => 'siret',
            '3', 'idprof3' => 'ape',
            '4' => 'idprof4',
            '5' => 'idprof5',
            '6' => 'idprof6',
            default => $idprof,
        };

        $sql = "SELECT COUNT(*) as nb FROM llx_societe WHERE " . $this->utils->sanitizeIdentifier($field) . " = " . $this->db->quote($value)
            . " AND entity IN (" . $this->config->getEntity('societe') . ")";
        if ($socid) {
            $sql .= " AND rowid <> " . (int) $socid;
        }

        return (int) $this->db->fetchOne($sql) > 0;
    }

    /**
     * Port of Societe::isACompany().
     */
    public function isACompany(Company $s): bool
    {
        $exts = $s->array_options['options_entitycompany'] ?? null;

        $typent_code = (string) ($s->typent_code ?? '');
        if ($typent_code === 'TE_PRIVATE' || (!empty($exts) && $this->config->getString('COMPANY_USE_PRIVATE_TO_DESCRIBE_COMPANY_TYPE'))) {
            return false;
        }

        if ($typent_code === 'TE_UNKNOWN') {
            return false;
        }
        if (in_array($typent_code, ['TE_STARTUP', 'TE_MEDIUM', 'TE_GROUP', 'TE_LARGE', 'TE_SMALL'])) {
            return true;
        }

        return ($s->typent_id ?? 0) > 0;
    }

    // ==================================================================
    //  create() — port of Societe::create
    // ==================================================================

    /**
     * @return int >0 id, <0 error
     */
    public function create(Company $s): int
    {
        $this->error = null;
        $this->errors = [];

        $s->entity = $this->config->entity();
        // upstream Societe::create() sets $this->status = 1 before normalizing
        if ($s->status === null) {
            $s->status = 1;
        }
        if (empty($s->status)) {
            $s->status = 0;
        }
        $s->name = trim((string) ($s->name ?? $s->nom ?? ''));
        $this->setUpperOrLowerCase($s);
        $s->client = (int) ($s->client ?? 0);
        $s->fournisseur = (int) ($s->fournisseur ?? 0);
        if (empty($s->import_key)) {
            $s->import_key = null;
        }
        if (empty($s->accountancy_code_buy)) {
            $s->accountancy_code_buy = '';
        }
        $s->accountancy_code_buy = trim((string) $s->accountancy_code_buy);
        if (empty($s->accountancy_code_sell)) {
            $s->accountancy_code_sell = '';
        }
        $s->accountancy_code_sell = trim((string) $s->accountancy_code_sell);
        if ($s->accountancy_code_customer_general == '-1') {
            $s->accountancy_code_customer_general = '';
        }
        if ($s->accountancy_code_supplier_general == '-1') {
            $s->accountancy_code_supplier_general = '';
        }

        // multicurrency
        if (!empty($s->multicurrency_code)) {
            $s->fk_multicurrency = (int) $this->utils->getIdFromCode($s->multicurrency_code, 'multicurrency', 'code', 'rowid');
        } else {
            $s->fk_multicurrency = 0;
            $s->multicurrency_code = '';
        }

        $s->date_creation = time();

        // upstream: get_codeclient()/get_codefournisseur() run when the
        // value is -1 or 'auto' and assign the numbering module's
        // getNextValue() result (only when SOCIETE_CODECLIENT_ADDON set).
        if ((string) $s->code_client === '-1' || $s->code_client === 'auto') {
            $newcode = $this->numbering->getNextCode($s, 0);
            if ($newcode !== null) {
                $s->code_client = $newcode;
            }
        }
        if ((string) $s->code_fournisseur === '-1' || $s->code_fournisseur === 'auto') {
            $newcode = $this->numbering->getNextCode($s, 1);
            if ($newcode !== null) {
                $s->code_fournisseur = $newcode;
            }
        }

        $result = $this->verify($s);
        if ($result < 0) {
            return -3;
        }

        $this->db->beginTransaction();
        try {
            $this->db->insert('llx_societe', [
                'nom' => $s->name,
                'name_alias' => $s->name_alias,
                'entity' => $s->entity,
                'datec' => date('Y-m-d H:i:s', (int) $s->date_creation),
                'fk_user_creat' => $this->config->apiUserId() ?: null,
                'fk_typent' => !empty($s->typent_id) ? (int) $s->typent_id : null,
                'canvas' => $s->canvas !== null ? (string) $s->canvas : null,
                'status' => (int) $s->status,
                'ref_ext' => !empty($s->ref_ext) ? (string) $s->ref_ext : null,
                'fk_stcomm' => 0,
                'fk_incoterms' => (int) ($s->fk_incoterms ?? 0),
                'location_incoterms' => (string) ($s->location_incoterms ?? ''),
                'import_key' => !empty($s->import_key) ? (string) $s->import_key : null,
                'fk_multicurrency' => (int) ($s->fk_multicurrency ?? 0),
                'multicurrency_code' => (string) ($s->multicurrency_code ?? ''),
                'ip' => empty($s->ip) ? null : (string) $s->ip,
                'vat_reverse_charge' => empty($s->vat_reverse_charge) ? 0 : 1,
                'accountancy_code_buy' => (string) ($s->accountancy_code_buy ?? ''),
                'accountancy_code_sell' => (string) ($s->accountancy_code_sell ?? ''),
            ]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($this->isDuplicateError($e)) {
                $this->error = 'ErrorCompanyNameAlreadyExists';

                return -1;
            }
            $this->error = $e->getMessage();

            return -2;
        }

        $s->id = (int) $this->db->lastInsertId();

        $ret = $this->update($s, (int) $s->id, 0, 1, 'add');

        // Addition of the assigned sales representative
        if (!empty($s->commercial_id) && $s->commercial_id != -1) {
            $this->addCommercial($s, (int) $s->commercial_id);
        }
        // Upstream also auto-assigns the creating user when they lack
        // 'societe client voir' rights — our API user always has them.

        if ($ret < 0) {
            $this->db->rollBack();

            return -4;
        }

        $this->db->commit();

        return $s->id;
    }

    // ==================================================================
    //  update() — port of Societe::update
    // ==================================================================

    /**
     * @param int $call_trigger unused (no trigger framework)
     * Returns <0 KO, >0 OK.
     *
     * @return int
     */
    public function update(Company $s, int $id, int $call_trigger = 1, int $allowmodcodeclient = 0, string $action = 'update'): int
    {
        $this->error = null;
        $this->errors = [];

        if ($id <= 0) {
            return -1;
        }
        $s->id = $id;

        // Clean parameters
        $s->id = $id;
        if (!empty($s->country)) {
            $s->country_id = (int) $this->utils->getIdFromCode((string) $s->country, 'c_country', 'code', 'rowid');
        }
        if (!empty($s->country_code)) {
            $s->country_id = (int) $this->utils->getIdFromCode((string) $s->country_code, 'c_country', 'code', 'rowid');
        }

        $s->name = trim((string) ($s->name ?? $s->nom ?? ''));
        $s->name_alias = trim((string) ($s->name_alias ?? ''));
        $s->ref_ext = trim((string) ($s->ref_ext ?? ''));
        $s->address = trim((string) ($s->address ?? ''));
        $s->zip = trim((string) ($s->zip ?? ''));
        $s->town = trim((string) ($s->town ?? ''));
        $s->state_id = (int) ($s->state_id ?? 0);
        $s->country_id = (int) ($s->country_id ?? 0);
        $s->phone = (string) preg_replace('/[\s\.]+/', '', (string) ($s->phone ?? ''));
        $s->phone_mobile = (string) preg_replace('/[\s\.]+/', '', (string) ($s->phone_mobile ?? ''));
        $s->fax = (string) preg_replace('/[\s\.]+/', '', (string) ($s->fax ?? ''));
        $s->email = trim((string) ($s->email ?? ''));
        $s->url = $s->url !== null && $s->url !== '' ? $this->utils->cleanUrl((string) $s->url) : '';
        $s->note_private = trim((string) ($s->note_private ?? ''));
        $s->note_public = trim((string) ($s->note_public ?? ''));
        $s->idprof1 = trim((string) ($s->idprof1 ?? ''));
        $s->idprof2 = trim((string) ($s->idprof2 ?? ''));
        $s->idprof3 = trim((string) ($s->idprof3 ?? ''));
        $s->idprof4 = trim((string) ($s->idprof4 ?? ''));
        $s->idprof5 = trim((string) ($s->idprof5 ?? ''));
        $s->idprof6 = trim((string) ($s->idprof6 ?? ''));
        $s->outstanding_limit = $s->outstanding_limit !== null && $s->outstanding_limit !== '' ? $this->utils->price2num($s->outstanding_limit) : null;
        $s->order_min_amount = $s->order_min_amount !== null && $s->order_min_amount !== '' ? $this->utils->price2num($s->order_min_amount) : null;
        $s->supplier_order_min_amount = $s->supplier_order_min_amount !== null && $s->supplier_order_min_amount !== '' ? $this->utils->price2num($s->supplier_order_min_amount) : null;
        $s->tva_assuj = (int) ($s->tva_assuj ?? 0);
        $s->tva_intra = trim((string) ($s->tva_intra ?? ''));
        $s->vat_reverse_charge = !empty($s->vat_reverse_charge) ? 1 : 0;
        $s->euid = trim((string) ($s->euid ?? ''));
        if (empty($s->status)) {
            $s->status = 0;
        }

        // multicurrency
        if (!empty($s->multicurrency_code)) {
            $s->fk_multicurrency = (int) $this->utils->getIdFromCode($s->multicurrency_code, 'multicurrency', 'code', 'rowid');
        } else {
            $s->fk_multicurrency = 0;
            $s->multicurrency_code = '';
        }

        $s->localtax1_assuj = (int) ($s->localtax1_assuj ?? 0);
        $s->localtax2_assuj = (int) ($s->localtax2_assuj ?? 0);
        $s->localtax1_value = $s->localtax1_value !== null ? (float) trim((string) $s->localtax1_value) : null;
        $s->localtax2_value = $s->localtax2_value !== null ? (float) trim((string) $s->localtax2_value) : null;
        $s->capital = $s->capital === null || $s->capital === '' ? null : (float) $s->capital;
        $s->effectif_id = (int) ($s->effectif_id ?? 0);
        $s->forme_juridique_code = (int) ($s->forme_juridique_code ?? 0);
        $s->barcode = trim((string) ($s->barcode ?? ''));

        // upstream: get_codeclient()/get_codefournisseur() run when the
        // value is -1 or 'auto' and assign the numbering module's
        // getNextValue() result (only when SOCIETE_CODECLIENT_ADDON set).
        if ((string) $s->code_client === '-1' || $s->code_client === 'auto') {
            $newcode = $this->numbering->getNextCode($s, 0);
            if ($newcode !== null) {
                $s->code_client = $newcode;
            }
        }
        if ((string) $s->code_fournisseur === '-1' || $s->code_fournisseur === 'auto') {
            $newcode = $this->numbering->getNextCode($s, 1);
            if ($newcode !== null) {
                $s->code_fournisseur = $newcode;
            }
        }

        $s->accountancy_code_buy = trim((string) ($s->accountancy_code_buy ?? ''));
        $s->accountancy_code_sell = trim((string) ($s->accountancy_code_sell ?? ''));
        if ($s->accountancy_code_customer_general == '-1') {
            $s->accountancy_code_customer_general = '';
        }
        $s->accountancy_code_customer_general = trim((string) ($s->accountancy_code_customer_general ?? ''));
        if ($s->accountancy_code_supplier_general == '-1') {
            $s->accountancy_code_supplier_general = '';
        }
        $s->accountancy_code_supplier_general = trim((string) ($s->accountancy_code_supplier_general ?? ''));

        $s->fk_incoterms = (int) ($s->fk_incoterms ?? 0);
        $s->location_incoterms = trim((string) ($s->location_incoterms ?? ''));

        if (!is_numeric($s->client) && !is_numeric($s->fournisseur)) {
            $this->error = 'BadValueForParameterClientOrSupplier';

            return -1;
        }

        $customer = false;
        $supplier = false;
        $codecomptaOK = true;

        if ($allowmodcodeclient && $s->client) {
            if (empty($s->code_compta_client) || $s->code_compta_client === '-1') {
                $ret = $this->numbering->getCodeCompta($s, 'customer');
                if ($ret < 0) {
                    return -1;
                }
            }
            $customer = true;
        }

        if ($allowmodcodeclient && $s->fournisseur) {
            if (empty($s->code_compta_fournisseur) || $s->code_compta_fournisseur === '-1') {
                $ret = $this->numbering->getCodeCompta($s, 'supplier');
                if ($ret < 0) {
                    return -1;
                }
            }
            $supplier = true;
        }

        $s->webservices_url = !empty($s->webservices_url) ? $this->utils->cleanUrl((string) $s->webservices_url) : '';
        $s->webservices_key = trim((string) ($s->webservices_key ?? ''));

        // Check name is required and codes are ok or unique.
        $result = 0;
        if ($action !== 'add' && $action !== 'merge') {
            $result = $this->verify($s);

            if (count($this->errors) > 0) {
                // Relieve errors on unchanged codes (oldcopy comparison)
                $oldcopy = $s->oldcopy;
                if (in_array('ErrorBadCustomerCodeSyntax', $this->errors, true) && $oldcopy instanceof Company && $oldcopy->code_client === $s->code_client) {
                    $key = array_search('ErrorBadCustomerCodeSyntax', $this->errors, true);
                    unset($this->errors[$key]);
                }
                if (in_array('ErrorBadSupplierCodeSyntax', $this->errors, true) && $oldcopy instanceof Company && $oldcopy->code_fournisseur === $s->code_fournisseur) {
                    $key = array_search('ErrorBadSupplierCodeSyntax', $this->errors, true);
                    unset($this->errors[$key]);
                }
                if (count($this->errors) === 0) {
                    $result = 0;
                }
            }
        }
        $this->setUpperOrLowerCase($s);
        if ($result < 0) {
            return -3;
        }

        $sql = "UPDATE llx_societe SET ";
        $sql .= "entity = " . ((int) $s->entity);
        $sql .= ",nom = " . $this->db->quote((string) $s->name);
        $sql .= ",name_alias = " . $this->db->quote((string) ($s->name_alias ?? ''));
        $sql .= ",ref_ext = " . (!empty($s->ref_ext) ? $this->db->quote((string) $s->ref_ext) : "null");
        $sql .= ",address = " . $this->db->quote((string) ($s->address ?? ''));
        $sql .= ",zip = " . (!empty($s->zip) ? $this->db->quote((string) $s->zip) : "null");
        $sql .= ",town = " . (!empty($s->town) ? $this->db->quote((string) $s->town) : "null");
        $sql .= ",fk_departement = " . ((!empty($s->state_id) && $s->state_id > 0) ? (int) $s->state_id : 'null');
        $sql .= ",fk_pays = " . ((!empty($s->country_id) && $s->country_id > 0) ? (int) $s->country_id : 'null');
        $sql .= ",phone = " . (!empty($s->phone) ? $this->db->quote((string) $s->phone) : "null");
        $sql .= ",phone_mobile = " . (!empty($s->phone_mobile) ? $this->db->quote((string) $s->phone_mobile) : "null");
        $sql .= ",fax = " . (!empty($s->fax) ? $this->db->quote((string) $s->fax) : "null");
        $sql .= ",email = " . (!empty($s->email) ? $this->db->quote((string) $s->email) : "null");
        $sql .= ",socialnetworks = " . $this->db->quote(json_encode($s->socialnetworks ?? []));
        $sql .= ",url = " . (!empty($s->url) ? $this->db->quote((string) $s->url) : "null");
        $sql .= ",parent = " . (($s->parent ?? 0) > 0 ? (int) $s->parent : "null");
        $sql .= ",note_private = " . (!empty($s->note_private) ? $this->db->quote((string) $s->note_private) : "null");
        $sql .= ",note_public = " . (!empty($s->note_public) ? $this->db->quote((string) $s->note_public) : "null");
        $sql .= ",siren = " . $this->db->quote((string) ($s->idprof1 ?? ''));
        $sql .= ",siret = " . $this->db->quote((string) ($s->idprof2 ?? ''));
        $sql .= ",ape = " . $this->db->quote((string) ($s->idprof3 ?? ''));
        $sql .= ",idprof4 = " . $this->db->quote((string) ($s->idprof4 ?? ''));
        $sql .= ",idprof5 = " . $this->db->quote((string) ($s->idprof5 ?? ''));
        $sql .= ",idprof6 = " . $this->db->quote((string) ($s->idprof6 ?? ''));
        $sql .= ",tva_assuj = " . ($s->tva_assuj !== '' ? $this->db->quote((string) $s->tva_assuj) : "null");
        $sql .= ",tva_intra = " . $this->db->quote((string) ($s->tva_intra ?? ''));
        $sql .= ",vat_reverse_charge = " . ($s->vat_reverse_charge !== '' ? $this->db->quote((string) $s->vat_reverse_charge) : '0');
        $sql .= ",euid = " . $this->db->quote((string) ($s->euid ?? ''));
        $sql .= ",status = " . ((int) $s->status);
        $sql .= ",localtax1_assuj = " . ($s->localtax1_assuj !== '' && $s->localtax1_assuj !== null ? $this->db->quote((string) $s->localtax1_assuj) : "null");
        $sql .= ",localtax2_assuj = " . ($s->localtax2_assuj !== '' && $s->localtax2_assuj !== null ? $this->db->quote((string) $s->localtax2_assuj) : "null");
        if ($s->localtax1_assuj == 1) {
            $sql .= ", localtax1_value = " . ($s->localtax1_value !== null && $s->localtax1_value !== '' ? (float) $s->localtax1_value : '0.000');
        } else {
            $sql .= ",localtax1_value = 0.000";
        }
        if ($s->localtax2_assuj == 1) {
            $sql .= ",localtax2_value = " . ($s->localtax2_value !== null && $s->localtax2_value !== '' ? (float) $s->localtax2_value : '0.000');
        } else {
            $sql .= ",localtax2_value = 0.000";
        }
        $sql .= ",capital = " . ($s->capital === null ? "null" : (float) $s->capital);
        $sql .= ",prefix_comm = " . (!empty($s->prefix_comm) ? $this->db->quote((string) $s->prefix_comm) : "null");
        $sql .= ",fk_effectif = " . (($s->effectif_id ?? 0) > 0 ? (int) $s->effectif_id : "null");

        if (isset($s->stcomm_id)) {
            $sql .= ",fk_stcomm=" . (int) $s->stcomm_id;
        }
        if (isset($s->typent_id)) {
            $sql .= ",fk_typent = " . (($s->typent_id ?? 0) > 0 ? (int) $s->typent_id : '0');
        }
        $sql .= ",fk_forme_juridique = " . (!empty($s->forme_juridique_code) ? $this->db->quote((string) $s->forme_juridique_code) : "null");
        $sql .= ",birth = " . ($s->birth !== null && $s->birth !== '' && $s->birth !== 0 ? $this->db->quote($this->idate((int) $s->birth, true)) : "null");
        $sql .= ",datec = " . $this->db->quote($this->idate((int) $s->date_creation));
        $sql .= ",canvas = " . (!empty($s->canvas) ? $this->db->quote((string) $s->canvas) : "null");
        $sql .= ",tms = " . $this->db->quote(date('Y-m-d H:i:s'));
        $sql .= ",client = " . (!empty($s->client) ? (int) $s->client : 0);
        $sql .= ",fournisseur = " . (!empty($s->fournisseur) ? (int) $s->fournisseur : 0);
        $sql .= ",barcode = " . (!empty($s->barcode) ? $this->db->quote((string) $s->barcode) : "null");
        $sql .= ",default_lang = " . (!empty($s->default_lang) ? $this->db->quote((string) $s->default_lang) : "null");
        $sql .= ",logo = " . (!empty($s->logo) ? $this->db->quote((string) $s->logo) : "null");
        $sql .= ",logo_squarred = " . (!empty($s->logo_squarred) ? $this->db->quote((string) $s->logo_squarred) : "null");
        $sql .= ",webservices_url = " . (!empty($s->webservices_url) ? $this->db->quote((string) $s->webservices_url) : "null");
        $sql .= ",webservices_key = " . (!empty($s->webservices_key) ? $this->db->quote((string) $s->webservices_key) : "null");
        $sql .= ",accountancy_code_sell = " . $this->db->quote((string) ($s->accountancy_code_sell ?? ''));
        $sql .= ",accountancy_code_buy = " . $this->db->quote((string) ($s->accountancy_code_buy ?? ''));
        if ($customer) {
            $sql .= ",accountancy_code_customer_general = " . (!empty($s->accountancy_code_customer_general) ? $this->db->quote((string) $s->accountancy_code_customer_general) : 'null');
            $sql .= ",code_compta = " . (!empty($s->code_compta_client) ? $this->db->quote((string) $s->code_compta_client) : 'null');
        }
        if ($supplier) {
            $sql .= ",accountancy_code_supplier_general = " . (!empty($s->accountancy_code_supplier_general) ? $this->db->quote((string) $s->accountancy_code_supplier_general) : 'null');
            $sql .= ",code_compta_fournisseur = " . ($s->code_compta_fournisseur != '' && $s->code_compta_fournisseur !== null ? $this->db->quote((string) $s->code_compta_fournisseur) : 'null');
        }
        $sql .= ",fk_prospectlevel = " . $this->db->quote((string) ($s->fk_prospectlevel ?? ''));
        $sql .= ",fk_user_modif = " . ($this->config->apiUserId() ? (int) $this->config->apiUserId() : 'null');
        $sql .= ",mode_reglement = " . (!empty($s->mode_reglement_id) ? $this->db->quote((string) $s->mode_reglement_id) : 'null');
        $sql .= ",cond_reglement = " . (!empty($s->cond_reglement_id) ? $this->db->quote((string) $s->cond_reglement_id) : 'null');
        $sql .= ",deposit_percent = " . ($s->deposit_percent !== null && $s->deposit_percent !== '' ? $this->db->quote((string) $s->deposit_percent) : 'null');
        $sql .= ",transport_mode = " . (!empty($s->transport_mode_id) ? $this->db->quote((string) $s->transport_mode_id) : 'null');
        $sql .= ",mode_reglement_supplier = " . (!empty($s->mode_reglement_supplier_id) ? $this->db->quote((string) $s->mode_reglement_supplier_id) : 'null');
        $sql .= ",cond_reglement_supplier = " . (!empty($s->cond_reglement_supplier_id) ? $this->db->quote((string) $s->cond_reglement_supplier_id) : 'null');
        $sql .= ",transport_mode_supplier = " . (!empty($s->transport_mode_supplier_id) ? $this->db->quote((string) $s->transport_mode_supplier_id) : 'null');
        $sql .= ",remise_client = " . ($s->remise_percent !== '' && $s->remise_percent !== null ? (float) $s->remise_percent : '0');
        $sql .= ",remise_supplier = " . (!empty($s->remise_supplier_percent) ? (float) $s->remise_supplier_percent : '0');
        $sql .= ",outstanding_limit = " . ($s->outstanding_limit !== null && $s->outstanding_limit !== '' ? (float) $s->outstanding_limit : 'null');
        $sql .= ",order_min_amount = " . ($s->order_min_amount !== null && $s->order_min_amount !== '' ? (float) $s->order_min_amount : 'null');
        $sql .= ",supplier_order_min_amount = " . ($s->supplier_order_min_amount !== null && $s->supplier_order_min_amount !== '' ? (float) $s->supplier_order_min_amount : 'null');
        $sql .= ",fk_shipping_method = " . (!empty($s->shipping_method_id) ? (int) $s->shipping_method_id : 'null');
        $sql .= ",fk_account = " . (!empty($s->fk_account) ? (int) $s->fk_account : 'null');
        $sql .= ",fk_warehouse = " . (!empty($s->fk_warehouse) ? (int) $s->fk_warehouse : 'null');
        $sql .= ",price_level = " . (!empty($s->price_level) ? (int) $s->price_level : 'null');
        $sql .= ",fk_multicurrency = " . ((int) ($s->fk_multicurrency ?? 0));
        $sql .= ",multicurrency_code = " . $this->db->quote((string) ($s->multicurrency_code ?? ''));
        $sql .= ",model_pdf = " . (!empty($s->model_pdf) ? $this->db->quote((string) $s->model_pdf) : 'null');
        $sql .= ",import_key = " . (!empty($s->import_key) ? $this->db->quote((string) $s->import_key) : 'null');

        if ($customer) {
            $sql .= ",code_client = " . (!empty($s->code_client) ? $this->db->quote((string) $s->code_client) : "null");
        }
        if ($supplier) {
            $sql .= ",code_fournisseur = " . (!empty($s->code_fournisseur) ? $this->db->quote((string) $s->code_fournisseur) : "null");
        }

        $sql .= " WHERE rowid = " . ((int) $id);

        try {
            $this->db->executeStatement($sql);
        } catch (\Throwable $e) {
            if ($this->isDuplicateError($e)) {
                $this->error = 'ErrorDuplicateField';

                return -1;
            }
            $this->error = $e->getMessage();

            return -2;
        }

        // extrafields
        $this->saveExtrafields('societe', $id, $s->array_options);

        return 1;
    }

    /**
     * Port of Societe::setUpperOrLowerCase() (CommonPeople).
     */
    public function setUpperOrLowerCase(Company $s): void
    {
        if ($this->config->getString('MAIN_TE_PRIVATE_FIRST_AND_LASTNAME_TO_UPPER')) {
            // not used for thirdparty names upstream in this flag path
        }
        if ($this->config->getString('MAIN_FIRST_TO_UPPER')) {
            $s->name = ucwords(strtolower((string) $s->name));
        }
        if ($this->config->getString('MAIN_ALL_TO_UPPER')) {
            $s->name = strtoupper((string) $s->name);
        }
        if ($this->config->getString('MAIN_ALL_TOWN_TO_UPPER')) {
            $s->address = $s->address !== null ? strtoupper((string) $s->address) : $s->address;
            $s->town = $s->town !== null ? strtoupper((string) $s->town) : $s->town;
        }
        if ($s->email !== null) {
            $s->email = strtolower((string) $s->email);
        }
    }

    // ==================================================================
    //  delete() — port of Societe::delete
    // ==================================================================

    /**
     * @return int 1 OK, 0 used, <0 error
     */
    public function delete(Company $s, int $id): int
    {
        $this->errors = [];

        $objectisused = $this->isObjectUsed($id);
        if ($objectisused > 0) {
            return 0;
        }

        $error = 0;
        $this->db->beginTransaction();
        try {
            // Categories links removed first
            $this->db->executeStatement('DELETE FROM llx_categorie_societe WHERE fk_soc = ' . (int) $id);
            $this->db->executeStatement('DELETE FROM llx_categorie_fournisseur WHERE fk_soc = ' . (int) $id);

            // Cascade tables owned by the societe
            foreach (['llx_societe_prices', 'llx_societe_account', 'llx_societe_rib', 'llx_societe_remise', 'llx_societe_remise_except', 'llx_societe_commerciaux'] as $t) {
                $this->db->executeStatement("DELETE FROM $t WHERE fk_soc = " . (int) $id);
            }
            // notifications + categories owned by the thirdparty
            $this->db->executeStatement('DELETE FROM llx_notify_def WHERE fk_soc = ' . (int) $id);
            $this->db->executeStatement('DELETE FROM llx_categorie WHERE fk_soc = ' . (int) $id);

            // contacts + their extrafields
            $contactIds = $this->db->fetchFirstColumn('SELECT rowid FROM llx_socpeople WHERE fk_soc = ' . (int) $id);
            foreach ($contactIds as $cid) {
                $this->db->executeStatement('DELETE FROM llx_socpeople_extrafields WHERE fk_object = ' . (int) $cid);
            }
            $this->db->executeStatement('DELETE FROM llx_socpeople WHERE fk_soc = ' . (int) $id);

            // extrafields of the thirdparty itself
            $this->db->executeStatement('DELETE FROM llx_societe_extrafields WHERE fk_object = ' . (int) $id);

            // unlink subsidiaries
            $this->db->executeStatement('UPDATE llx_societe SET parent = NULL WHERE parent = ' . (int) $id);

            $this->db->executeStatement('DELETE FROM llx_societe WHERE rowid = ' . (int) $id);

            $this->db->commit();

            return 1;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->errors[] = $e->getMessage();
            $this->error = $e->getMessage();

            return -1;
        }
    }

    /**
     * Port of CommonObject::isObjectUsed(): counts rows in child tables.
     * Only tables existing in the CRM schema are scanned; upstream's other
     * child tables (propal, commande, facture, ...) belong to domains this
     * service does not own and are never created.
     */
    public function isObjectUsed(int $id): int
    {
        $childtables = [
            'supplier_proposal', 'propal', 'commande', 'facture', 'facture_rec',
            'contrat', 'fichinter', 'facture_fourn', 'commande_fournisseur',
            'projet', 'expedition', 'prelevement_lignes', 'adherent',
        ];
        $haschild = 0;
        foreach ($childtables as $table) {
            $tbl = 'llx_' . $table;
            if (!$this->tableExists($tbl)) {
                continue;
            }
            $cnt = (int) $this->db->fetchOne("SELECT COUNT(*) FROM $tbl WHERE fk_soc = " . (int) $id);
            $haschild += $cnt;
        }

        return $haschild;
    }

    // ==================================================================
    //  mergeCompany() — port
    // ==================================================================

    /**
     * @return int 0 OK, <0 KO
     */
    public function mergeCompany(Company $target, int $socOriginId, CategoryService $categories): int
    {
        $socOrigin = new Company();
        if ($this->fetch($socOrigin, $socOriginId) < 1) {
            $this->error = 'ErrorRecordNotFound';

            return -1;
        }

        $error = 0;
        $this->db->beginTransaction();
        try {
            $target->client |= $socOrigin->client;
            $target->fournisseur |= $socOrigin->fournisseur;

            foreach (
                [
                'address', 'zip', 'town', 'state_id', 'country_id', 'phone', 'phone_mobile', 'fax', 'email', 'socialnetworks', 'url', 'barcode',
                'idprof1', 'idprof2', 'idprof3', 'idprof4', 'idprof5', 'idprof6',
                'tva_intra', 'euid', 'effectif_id', 'forme_juridique', 'remise_percent', 'remise_supplier_percent', 'mode_reglement_supplier_id', 'cond_reglement_supplier_id', 'name_bis',
                'stcomm_id', 'outstanding_limit', 'order_min_amount', 'supplier_order_min_amount', 'price_level', 'parent', 'default_lang', 'ref', 'ref_ext', 'import_key', 'fk_incoterms', 'fk_multicurrency',
                'code_client', 'code_fournisseur', 'code_compta', 'code_compta_fournisseur',
                'model_pdf', 'webservices_url', 'webservices_key', 'accountancy_code_sell', 'accountancy_code_buy', 'typent_id',
                ] as $property
            ) {
                if (empty($target->$property)) {
                    $target->$property = $socOrigin->$property;
                }
            }
            if ($target->typent_id == -1) {
                $target->typent_id = $socOrigin->typent_id;
            }
            foreach (['note_public', 'note_private'] as $property) {
                $target->$property = $this->utils->dolConcatdesc((string) ($target->$property ?? ''), (string) ($socOrigin->$property ?? ''));
            }

            // Merge extrafields
            foreach ($socOrigin->array_options as $key => $val) {
                if (empty($target->array_options[$key])) {
                    $target->array_options[$key] = $val;
                }
            }

            if (empty($target->name_bis) && $target->name != $socOrigin->name) {
                $target->name_bis = $target->name;
            }

            // Merge categories
            $custcats = array_merge($categories->containing($target->id, 'customer', 'id'), $categories->containing($socOrigin->id, 'customer', 'id'));
            $categories->setCategories($custcats, 'customer', $target->id);
            $suppcats = array_merge($categories->containing($target->id, 'supplier', 'id'), $categories->containing($socOrigin->id, 'supplier', 'id'));
            $categories->setCategories($suppcats, 'supplier', $target->id);

            // Clean codes on origin if duplicated
            if (
                $socOrigin->code_client === $target->code_client
                || $socOrigin->code_fournisseur === $target->code_fournisseur
                || $socOrigin->barcode === $target->barcode
            ) {
                $socOrigin->code_client = '';
                $socOrigin->code_fournisseur = '';
                $socOrigin->barcode = '';
                $this->update($socOrigin, (int) $socOrigin->id, 0, 1, 'merge');
            }

            // Children companies
            if (!$this->config->getString('SOCIETE_DISABLE_PARENTCOMPANY')) {
                foreach ($this->getChildrenForCompany((int) $socOrigin->id) as $childId) {
                    $this->db->executeStatement('UPDATE llx_societe SET parent = ' . (int) $target->id . ' WHERE rowid = ' . (int) $childId);
                }
            }

            $result = $this->update($target, (int) $target->id, 0, 1, 'merge');
            if ($result < 0) {
                $error++;
            }

            if (!$error) {
                // Move links on CRM-owned tables (upstream loops over object
                // classes; only the ones that live in this service are moved).
                foreach (['llx_societe_commerciaux', 'llx_societe_prices', 'llx_societe_remise', 'llx_societe_remise_except', 'llx_societe_rib', 'llx_societe_account', 'llx_notify_def', 'llx_socpeople'] as $tbl) {
                    if (!$this->tableExists($tbl)) {
                        continue;
                    }
                    // dedup commerciaux before moving
                    if ($tbl === 'llx_societe_commerciaux') {
                        $this->db->executeStatement(
                            'DELETE FROM llx_societe_commerciaux WHERE fk_soc = ' . (int) $target->id
                            . ' AND fk_user IN (SELECT fk_user FROM (SELECT fk_user FROM llx_societe_commerciaux WHERE fk_soc = ' . (int) $socOrigin->id . ') t)',
                        );
                    }
                    $this->db->executeStatement("UPDATE $tbl SET fk_soc = " . (int) $target->id . " WHERE fk_soc = " . (int) $socOrigin->id);
                }
            }

            if (!$error && $this->delete($socOrigin, (int) $socOrigin->id) < 1) {
                $this->error = (string) ($socOrigin->error ?? $this->error);
                $error++;
            }

            if ($error) {
                $this->db->rollBack();
                $this->error = 'ErrorsThirdpartyMerge';

                return -1;
            }

            $this->db->commit();

            return 0;
        } catch (\Throwable $e) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            $this->error = 'ErrorsThirdpartyMerge';
            $this->errors[] = $e->getMessage();

            return -1;
        }
    }

    // ==================================================================
    //  Misc ports
    // ==================================================================

    /**
     * Port of getSalesRepresentatives() for mode 0 (list).
     *
     * @return array<int, array<string, mixed>>|list<int> rep rows for mode 0, rowid list for mode 1
     */
    public function getSalesRepresentatives(int $socid, int $mode = 0): array
    {
        $sql = 'SELECT u.rowid, u.login, u.lastname, u.firstname, u.office_phone, u.job, u.email, u.statut as status, u.entity, u.photo, u.gender, u.office_fax, u.user_mobile, u.personal_mobile'
            . ' FROM llx_societe_commerciaux sc, llx_user u'
            . ' WHERE u.entity IN (0,' . $this->config->entity() . ') AND u.rowid = sc.fk_user AND sc.fk_soc = ' . (int) $socid
            . ' ORDER BY u.lastname, u.firstname';

        $rows = $this->db->fetchAllAssociative($sql);
        if ($mode == 1) {
            return array_map(static fn ($r) => (int) $r['rowid'], $rows);
        }
        $rep = [];
        foreach ($rows as $obj) {
            $rep[] = [
                'id' => (int) $obj['rowid'],
                'lastname' => $obj['lastname'],
                'firstname' => $obj['firstname'],
                'email' => $obj['email'],
                'phone' => $obj['office_phone'],
                'office_phone' => $obj['office_phone'],
                'office_fax' => $obj['office_fax'],
                'user_mobile' => $obj['user_mobile'],
                'personal_mobile' => $obj['personal_mobile'],
                'job' => $obj['job'],
                'statut' => $obj['status'],
                'status' => $obj['status'],
                'entity' => (int) $obj['entity'],
                'login' => $obj['login'],
                'photo' => $obj['photo'],
                'gender' => $obj['gender'],
            ];
        }

        return $rep;
    }

    /**
     * Port of add_commercial().
     */
    public function addCommercial(Company $s, int $commid): int
    {
        if (($s->id ?? 0) > 0 && $commid > 0) {
            try {
                $this->db->executeStatement('DELETE FROM llx_societe_commerciaux WHERE fk_soc = ' . (int) $s->id . ' AND fk_user = ' . (int) $commid);
                $this->db->executeStatement('INSERT INTO llx_societe_commerciaux (fk_soc, fk_user) VALUES (' . (int) $s->id . ', ' . (int) $commid . ')');

                return 1;
            } catch (\Throwable) {
                return -1;
            }
        }

        return 0;
    }

    /**
     * Port of del_commercial().
     */
    public function delCommercial(Company $s, int $commid): int
    {
        if (($s->id ?? 0) > 0 && $commid > 0) {
            try {
                $this->db->executeStatement('DELETE FROM llx_societe_commerciaux WHERE fk_soc = ' . (int) $s->id . ' AND fk_user = ' . (int) $commid);
            } catch (\Throwable) {
                return -1;
            }
        }

        return 1;
    }

    /** Port of setPriceLevel(). */
    public function setPriceLevel(Company $s, int $priceLevel): int
    {
        if ($s->id) {
            $this->db->executeStatement('UPDATE llx_societe SET price_level = ' . (int) $priceLevel . ' WHERE rowid = ' . (int) $s->id);
            $this->db->executeStatement('INSERT INTO llx_societe_prices (datec, fk_soc, price_level, fk_user_author) VALUES (' . $this->db->quote(date('Y-m-d H:i:s')) . ', ' . (int) $s->id . ', ' . (int) $priceLevel . ', ' . ($this->config->apiUserId() ?: 'null') . ')');

            return 1;
        }

        return -1;
    }

    /** Port of setParent() + validateFamilyTree(). */
    public function setParent(Company $s, ?int $id): int
    {
        if (!$s->id) {
            return -1;
        }
        if ($id !== null && $id > 0) {
            $sameparent = $this->validateFamilyTree($id, (int) $s->id);
            if ($sameparent < 0) {
                return -1;
            }
            if ($sameparent == 1) {
                return -1;
            }
        }
        $this->db->executeStatement('UPDATE llx_societe SET parent = ' . (($id ?? 0) > 0 ? (int) $id : 'null') . ' WHERE rowid = ' . (int) $s->id);
        $s->parent = $id;

        return 1;
    }

    public function validateFamilyTree(int $idparent, int $idchild, int $counter = 0): int
    {
        if ($counter > 100) {
            return -1;
        }
        $row = $this->db->fetchAssociative('SELECT parent FROM llx_societe WHERE rowid = ' . (int) $idparent);
        if ($row === false) {
            return -1;
        }
        if ($row['parent'] === null || $row['parent'] === '' || $row['parent'] === 0) {
            return 0;
        }
        if ((int) $row['parent'] === $idchild) {
            return 1;
        }

        return $this->validateFamilyTree((int) $row['parent'], $idchild, $counter + 1);
    }

    /** @return int[] */
    public function getChildrenForCompany(int $companyId): array
    {
        return array_map('intval', $this->db->fetchFirstColumn('SELECT rowid FROM llx_societe WHERE parent = ' . (int) $companyId));
    }

    /** Port of set_remise_client(): sets the customer discount + history row. */
    public function setRemiseClient(Company $s, float $remise, string $note): int
    {
        $note = trim($note);
        if (!$note) {
            $this->error = 'ErrorFieldRequired NoteReason';

            return -2;
        }
        if (!$s->id) {
            return -1;
        }

        $this->db->beginTransaction();
        try {
            $this->db->executeStatement('UPDATE llx_societe SET remise_client = ' . $this->db->quote((string) $remise) . ' WHERE rowid = ' . (int) $s->id);
            $this->db->executeStatement('INSERT INTO llx_societe_remise (entity, datec, fk_soc, remise_client, note, fk_user_author) VALUES ('
                . $this->config->entity() . ', ' . $this->db->quote(date('Y-m-d H:i:s')) . ', ' . (int) $s->id . ', ' . $this->db->quote((string) $remise) . ', ' . $this->db->quote($note) . ', ' . ($this->config->apiUserId() ?: 'null') . ')');
            $this->db->commit();

            return 1;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->error = $e->getMessage();

            return -1;
        }
    }

    /** Port of set_remise_supplier(). */
    public function setRemiseSupplier(Company $s, float $remise, string $note): int
    {
        $note = trim($note);
        if (!$note) {
            $this->error = 'ErrorFieldRequired NoteReason';

            return -2;
        }
        if (!$s->id) {
            return -1;
        }
        if (!$this->tableExists('llx_societe_remise_supplier')) {
            return -1;
        }

        $this->db->beginTransaction();
        try {
            $this->db->executeStatement('UPDATE llx_societe SET remise_supplier = ' . (float) $remise . ' WHERE rowid = ' . (int) $s->id);
            $this->db->executeStatement('INSERT INTO llx_societe_remise_supplier (entity, datec, fk_soc, remise_supplier, note, fk_user_author) VALUES ('
                . $this->config->entity() . ', ' . $this->db->quote(date('Y-m-d H:i:s')) . ', ' . (int) $s->id . ', ' . (float) $remise . ', ' . $this->db->quote($note) . ', ' . ($this->config->apiUserId() ?: 'null') . ')');
            $this->db->commit();

            return 1;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->error = $e->getMessage();

            return -1;
        }
    }

    // ==================================================================
    //  code checks (verify() helpers)
    // ==================================================================

    public function checkCodeClient(Company $s): int
    {
        $code = (string) ($s->code_client ?? '');
        $res = $this->numbering->verifCode($code, $s, 0);
        if ($res) {
            $this->error = $this->numbering->error;
            foreach ($this->numbering->errors as $e) {
                $this->errors[] = $e;
            }
        }
        $s->code_client = $code;

        return $res;
    }

    public function checkCodeFournisseur(Company $s): int
    {
        $code = (string) ($s->code_fournisseur ?? '');
        $res = $this->numbering->verifCode($code, $s, 1);
        if ($res) {
            $this->error = $this->numbering->error;
            foreach ($this->numbering->errors as $e) {
                $this->errors[] = $e;
            }
        }
        $s->code_fournisseur = $code;

        return $res;
    }

    // ==================================================================
    //  helpers
    // ==================================================================

    public function saveExtrafields(string $tableElement, int $fkObject, array $arrayOptions): void
    {
        $table = 'llx_' . $tableElement . '_extrafields';
        if (!$this->tableExists($table)) {
            return;
        }

        $columns = $this->extraColumns($table);
        // upstream keys are 'options_<column>' — strip the prefix
        $normalized = [];
        foreach ($arrayOptions as $key => $value) {
            $normalized[str_starts_with($key, 'options_') ? substr($key, 8) : $key] = $value;
        }
        $values = array_intersect_key($normalized, array_flip($columns));

        $this->db->executeStatement("DELETE FROM $table WHERE fk_object = " . (int) $fkObject);
        if ($values !== []) {
            $values['fk_object'] = $fkObject;
            $this->db->insert($table, $values);
        }
    }

    /** @return string[] */
    private function extraColumns(string $table): array
    {
        $cols = array_keys($this->db->createSchemaManager()->listTableColumns($table));

        return array_values(array_diff($cols, ['rowid', 'tms', 'fk_object', 'import_key']));
    }

    public function tableExists(string $table): bool
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $cache[$table] = $this->db->createSchemaManager()->tablesExist([$table]);
        }

        return $cache[$table];
    }

    private function isDuplicateError(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'Duplicate entry')
            || ($e->getCode() === '1062')
            || ($e->getCode() === 1062)
            || str_contains((string) $e->getMessage(), '1062');
    }

    /** Convert a DB datetime to a timestamp like $db->jdate(). */
    private function jdate(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        $ts = strtotime((string) $value);

        return $ts === false ? null : $ts;
    }

    /** Format a timestamp like $db->idate(). */
    private function idate(int $ts, bool $dayOnly = false): string
    {
        return $dayOnly ? date('Y-m-d', $ts) : date('Y-m-d H:i:s', $ts);
    }
}
