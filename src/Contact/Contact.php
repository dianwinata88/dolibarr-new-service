<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * Data holder mirroring upstream Contact's public property surface.
 *
 * Upstream returns json_encode($contact) after cleaning, so the response
 * body exposes every public property. They are declared here in the same
 * order as the parent class (CommonObject), the traits and Contact itself,
 * so the emitted JSON keeps the same key set and order.
 *
 * Dynamic properties are allowed on purpose: the API write paths assign
 * request fields by name exactly like upstream ($object->$field = ...).
 */
#[\AllowDynamicProperties]
class Contact
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
    public $element = 'contact';
    public $fk_element = 'fk_socpeople';
    public $element_for_permission = null;
    public $table_element = 'socpeople';
    public $table_rowid = null;
    public $table_element_line = '';
    public $picto = 'contact';
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

    // ----- CommonSocialNetworks / CommonPeople traits -----

    /** @var array<string, mixed>|null */
    public $socialnetworks = null;
    public $address = null;
    public $zip = null;
    public $town = null;
    public $email = null;
    public $url = null;
    public $gender = null;

    // ----- Contact public properties, declaration order -----

    public $TRIGGER_PREFIX = 'CONTACT_MODIFY';
    public $birthday_alert = null;
    /** @var array<int|string, mixed> */
    public $cacheprospectstatus = [];
    public $fk_prospectlevel = null;
    public $stcomm_id = null;
    public $statut_commercial = null;
    public $stcomm_picto = null;
    public $civility = null;
    public $name_alias = null;
    public $fullname = null;
    public $poste = null;
    public $socid = null;
    public $fk_soc = null;
    public $socname = null;
    public $code = null;
    public $mail = null;
    public $no_email = null;
    public $photo = null;
    public $phone_pro = null;
    public $phone_perso = null;
    public $phone_mobile = null;
    public $fax = null;
    public $priv = null;
    public $birthday = null;
    public $default_lang = null;
    public $ref_facturation = null;
    public $ref_contrat = null;
    public $ref_commande = null;
    public $ref_propal = null;
    public $user_id = null;
    public $user_login = null;
    public $ip = null;
    /** @var array<int, array{id:int|string,socid:int|string,element:string,source:string,code:string,label:string}>|null */
    public $roles = null;
    public $fk_departement = null;
    public $fk_pays = null;
}
