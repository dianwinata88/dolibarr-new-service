<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * Turns a hydrated Contact into the response array json_encode'd by
 * upstream — i.e. get_object_vars() of the Contact object minus the keys
 * removed by _cleanObjectDatas() (common list + the Contacts::get
 * additions).
 */
final class ContactSerializer
{
    /** Properties removed by CommonObject::_cleanObjectDatas / api.class.php _cleanObjectDatas parent call. */
    private const COMMON_UNSET = [
        'db', 'isextrafieldmanaged', 'ismultientitymanaged', 'restrictiononfksoc',
        'table_rowid', 'childtablesoncascade', 'picto', 'element',
        'element_for_permission', 'fk_element', 'table_element',
        'table_element_line', 'class_element_line', 'fields', 'fk_fields',
        'rowid', 'pass', 'pass_crypted', 'pass_indatabase', 'pass_indatabase_crypted', 'pass_temp',
        'linkedObjects', 'linkedObjectsIds', 'oldcopy', 'oldref', 'error', 'errors',
        'errorhidden', 'errorsstring', 'warning', 'warnings', 'TRIGGER_PREFIX',
        'ref_previous', 'ref_next', 'imgWidth', 'imgHeight', 'barcode_type_code',
        'barcode_type_label', 'mode_reglement', 'cond_reglement', 'note',
        'contact', 'thirdparty', 'warehouse', 'project', 'fk_projet', 'author',
        'timespent_id', 'timespent_old_duration', 'timespent_duration', 'timespent_date',
        'timespent_datehour', 'timespent_withhour', 'timespent_fk_user', 'timespent_note',
        'sendtoid', 'name_bis', 'newref', 'oldref', 'alreadypaid', 'openid',
        'fk_bank', 'showphoto_on_popup', 'nb', 'nbphoto', 'output', 'extraparams',
        'tpl', 'skip_update_total', 'context', 'next_prev_filter',
        'module', 'origin_object', 'origin', 'linked_objects',
        'region', 'region_code', 'country', 'state', 'state_code',
        'departement', 'departement_code', 'fk_delivery_address', 'fk_multicurrency',
        'libelle_statut', 'libelle_paiement', 'labelStatus', 'labelStatusShort',
        'actionmsg', 'actionmsg2', 'prefix_comm', 'stats_propale', 'stats_commande',
        'stats_contrat', 'stats_facture', 'stats_commande_fournisseur',
        'stats_reception', 'stats_mrptoconsume', 'stats_mrptoproduce', 'stats_bom',
        'stats_mo', 'stats_expedition', 'stats_facturerec', 'stats_facture_fournisseur',
        'stats_facturefournrec', 'stats_proposal_supplier', 'fieldsforcombobox',
        'regeximgext',
    ];

    /** Properties removed by Contacts::_cleanObjectDatas. */
    private const CONTACT_UNSET = [
        'total_ht', 'total_tva', 'total_localtax1', 'total_localtax2',
        'total_ttc', 'note', 'lines', 'thirdparty',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Contact $c): array
    {
        $data = get_object_vars($c);

        foreach (self::COMMON_UNSET as $k) {
            unset($data[$k]);
        }
        foreach (self::CONTACT_UNSET as $k) {
            unset($data[$k]);
        }

        // dynamic key 'errorsstring'
        unset($data['errorsstring']);

        return $data;
    }

    /**
     * Port of _filterObjectProperties(): when $properties is a comma list,
     * keep only those keys.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function filterProperties(array $data, string $properties): array
    {
        $keep = array_map('trim', explode(',', $properties));

        return array_intersect_key($data, array_flip($keep));
    }
}
