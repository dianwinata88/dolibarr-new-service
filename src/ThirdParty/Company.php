<?php

declare(strict_types=1);

namespace App\ThirdParty;

/**
 * Data holder mirroring upstream Societe's public property surface.
 *
 * Upstream returns json_encode($societe) after cleaning, so the response
 * body exposes every public property. They are declared here in the same
 * order as the parent class (CommonObject), the traits and Societe itself,
 * so the emitted JSON keeps the same key set and order.
 *
 * Dynamic properties are allowed on purpose: the API write paths assign
 * request fields by name exactly like upstream ($object->$field = ...).
 */
#[\AllowDynamicProperties]
class Company
{
    // ----- CommonObject public properties, declaration order -----

    public $module = null;
    public $db = null;
    public $id = null;
    public $entity = null;
    public $error = null;
    public $errorhidden = null;
    /** @var string[] */
    public $errors = [];
    /** @var string[] */
    public $warnings = [];
    public $element = 'societe';
    public $fk_element = 'fk_soc';
    public $element_for_permission = null;
    public $table_element = 'societe';
    public $table_rowid = null;
    public $table_element_line = '';
    public $ismultientitymanaged = 1;
    public $import_key = null;
    /** @var array<string, mixed> */
    public $array_options = [];
    /** @var array<string, mixed> */
    public $fields = [];
    /** @var array<string, mixed>|null */
    public $array_languages = null;
    /** @var array<int>|null */
    public $contacts_ids = null;
    /** @var array<int>|null */
    public $contacts_ids_internal = null;
    /** @var array<string, mixed>|null */
    public $linked_objects = null;
    /** @var array<string, mixed>|null */
    public $other_linked_objects = null;
    /** @var array<string, mixed> */
    public $linkedObjectsIds = [];
    /** @var array<string, mixed> */
    public $linkedObjects = [];
    public $oldcopy = null;
    public $oldref = null;
    public $restrictiononfksoc = 1;
    /** @var array<string, mixed> */
    public $context = [];
    public $actionmsg = null;
    public $actionmsg2 = null;
    public $canvas = null;
    public $project = null;
    public $fk_project = null;
    public $fk_projet = null;
    public $contact = null;
    public $contact_id = null;
    public $thirdparty = null;
    public $user = null;
    public $product = null;
    public $warehouse = null;
    public $origin_type = null;
    public $origin_id = null;
    public $origin_object = null;
    public $origin = null;
    public $ref = null;
    public $ref_ext = null;
    public $ref_previous = null;
    public $ref_next = null;
    public $newref = null;
    public $statut = null;
    public $status = null;
    public $country = null;
    public $country_id = null;
    public $country_code = null;
    public $state = null;
    public $state_id = null;
    public $state_code = null;
    public $region_id = null;
    public $region_code = null;
    public $region = null;
    public $barcode_type = null;
    public $barcode_type_code = null;
    public $barcode_type_label = null;
    public $barcode_type_coder = null;
    public $mode_reglement_id = null;
    public $cond_reglement_id = null;
    public $demand_reason_id = null;
    public $transport_mode_id = null;
    public $fk_delivery_address = null;
    public $shipping_method_id = null;
    public $shipping_method = null;
    public $fk_multicurrency = null;
    public $multicurrency_code = null;
    public $multicurrency_tx = null;
    public $multicurrency_total_ht = null;
    public $multicurrency_total_tva = null;
    public $multicurrency_total_localtax1 = null;
    public $multicurrency_total_localtax2 = null;
    public $multicurrency_total_ttc = null;
    public $model_pdf = null;
    public $last_main_doc = null;
    public $fk_bank = null;
    public $fk_account = null;
    public $note_public = null;
    public $note_private = null;
    public $note = null;
    public $total_ht = null;
    public $total_tva = null;
    public $total_localtax1 = null;
    public $total_localtax2 = null;
    public $total_ttc = null;
    public $lines = null;
    public $actiontypecode = null;
    /** @var array<mixed> */
    public $comments = [];
    public $name = null;
    public $lastname = null;
    public $firstname = null;
    public $civility_id = null;
    public $civility_code = null;
    public $date_creation = null;
    public $date_validation = null;
    public $date_modification = null;
    public $tms = null;
    public $date_cloture = null;
    /** @var float|null absolute discounts available (computed on fetch like upstream) */
    public $absolute_discount = null;
    /** @var float|null absolute credit notes available (computed on fetch like upstream) */
    public $absolute_creditnote = null;
    public $user_creation_id = null;
    public $user_validation_id = null;
    public $user_closing_id = null;
    public $user_modification_id = null;
    public $fk_user_creat = null;
    public $fk_user_modif = null;
    public $next_prev_filter = null;
    public $specimen = 0;
    public $sendtoid = null;
    public $alreadypaid = null;
    public $totalpaid = null;
    public $totalpaid_multicurrency = null;
    /** @var array<int, string> */
    public $labelStatus = [];
    /** @var array<int, string> */
    public $labelStatusShort = [];
    public $tpl = null;
    public $showphoto_on_popup = null;
    /** @var array<string, int> */
    public $nb = [];
    public $nbphoto = null;
    public $output = null;
    /** @var array<string, mixed> */
    public $extraparams = [];
    public $cond_reglement_supplier_id = null;
    public $deposit_percent = null;
    public $warehouse_id = null;
    public $isextrafieldmanaged = 1;
    public $langtouse = null;

    // ----- CommonIncoterm / CommonSocialNetworks / CommonPeople traits -----

    public $fk_incoterms = null;
    public $label_incoterms = null;
    public $location_incoterms = null;
    /** @var array<string, mixed>|null */
    public $socialnetworks = null;
    public $address = null;
    public $zip = null;
    public $town = null;
    public $email = null;
    public $url = null;

    // ----- Societe public properties, declaration order -----

    public $TRIGGER_PREFIX = null;
    /** @var array<int> */
    public $supplierCategories = [];
    public $prefixCustomerIsRequired = false;
    public $nom = null;
    public $name_alias = null;
    public $particulier = 0;
    public $departement_code = null;
    public $departement = null;
    public $pays = null;
    public $phone = null;
    public $phone_mobile = null;
    public $fax = null;
    public $no_email = 0;
    public $skype = null;
    public $twitter = null;
    public $facebook = null;
    public $linkedin = null;
    public $barcode = null;
    public $idprof1 = null;
    public $siren = null;
    public $idprof2 = null;
    public $siret = null;
    public $idprof3 = null;
    public $ape = null;
    public $idprof4 = null;
    public $idprof5 = null;
    public $idprof6 = null;
    public $idprof7 = null;
    public $idprof8 = null;
    public $idprof9 = null;
    public $idprof10 = null;
    public $socialobject = null;
    public $prefix_comm = null;
    public $tva_assuj = 1;
    public $tva_intra = null;
    public $euid = null;
    public $vat_reverse_charge = 0;
    public $localtax1_assuj = null;
    public $localtax1_value = null;
    public $localtax2_assuj = null;
    public $localtax2_value = null;
    public $managers = null;
    public $capital = null;
    public $typent_id = 0;
    public $typent_code = null;
    public $effectif = null;
    public $effectif_id = 0;
    public $forme_juridique_code = 0;
    public $forme_juridique = null;
    public $birth = null;
    public $remise_percent = 0;
    public $remise_supplier_percent = null;
    public $mode_reglement_supplier_id = null;
    public $transport_mode_supplier_id = null;
    public $fk_prospectlevel = null;
    public $name_bis = null;
    public $user_modification = null;
    public $user_creation = null;
    public $client = 0;
    public $prospect = 0;
    public $fournisseur = 0;
    public $code_client = null;
    public $code_fournisseur = null;
    public $code_compta_client = null;
    public $accountancy_code_customer_general = null;
    public $accountancy_code_customer = null;
    public $code_compta_fournisseur = null;
    public $accountancy_code_supplier_general = null;
    public $accountancy_code_supplier = null;
    public $code_compta_product = null;
    public $stcomm_id = null;
    public $stcomm_picto = null;
    public $status_prospect_label = null;
    public $price_level = null;
    public $outstanding_limit = null;
    public $order_min_amount = null;
    public $supplier_order_min_amount = null;
    public $commercial_id = null;
    public $parent = null;
    public $default_lang = null;
    public $ip = null;
    public $webservices_url = null;
    public $webservices_key = null;
    public $logo = null;
    public $logo_small = null;
    public $logo_mini = null;
    public $logo_squarred = null;
    public $logo_squarred_small = null;
    public $logo_squarred_mini = null;
    public $accountancy_code_sell = null;
    public $accountancy_code_buy = null;
    public $currency_code = null;
    public $fk_warehouse = null;
    public $termsofsale = null;
    /** @var array<mixed> */
    public $partnerships = [];
    public $bank_account = null;
    public $code_compta = null;
}
