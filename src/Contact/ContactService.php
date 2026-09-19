<?php

declare(strict_types=1);

namespace App\Contact;

use Doctrine\DBAL\Connection;

/**
 * Port of the parts of Contact (htdocs/contact/class/contact.class.php) used
 * by the contacts API: fetch/create/update/update_perso/delete, fetchRoles/
 * updateRoles/getContactRoles, load_ref_elements, no_email handling and
 * initAsSpecimen, plus _checkAccessToResource/checkUserAccessToObject.
 *
 * Triggers, LDAP sync, canvases and the linked-user sync in update() are not
 * ported (no event bus / no user module in this service). $this->error /
 * $this->errors keep the upstream strings.
 */
final class ContactService
{
    public string $error = '';
    /** @var string[] */
    public array $errors = [];

    public function __construct(
        private readonly Connection $db,
        private readonly DolibarrConfig $config,
    ) {
    }

    /**
     * Port of Contact::fetch(). Returns >0 OK, 0 not found, <0 error.
     */
    public function fetch(Contact $c, int $rowid = 0, string $refExt = '', string $email = '', int $socid = 0): int
    {
        if ($rowid < 0) {
            $this->error = 'BadParameter';

            return -1;
        }

        $sql = 'SELECT c.rowid, c.entity, c.fk_soc, c.ref_ext, c.civility as civility_code, c.name_alias, c.lastname, c.firstname,';
        $sql .= ' c.address, c.statut as status, c.zip, c.town,';
        $sql .= ' c.fk_pays as country_id,';
        $sql .= ' c.fk_departement as state_id,';
        $sql .= ' c.birthday,';
        $sql .= ' c.poste, c.phone, c.phone_perso, c.phone_mobile, c.fax, c.email,';
        $sql .= ' c.socialnetworks,';
        $sql .= ' c.photo,';
        $sql .= ' c.priv, c.note_private, c.note_public, c.default_lang, c.canvas,';
        $sql .= ' c.fk_prospectlevel, c.fk_stcommcontact, st.libelle as stcomm, st.picto as stcomm_picto,';
        $sql .= ' c.import_key,';
        $sql .= ' c.datec as date_creation, GREATEST(c.tms, cef.tms) as date_modification, c.fk_user_creat, c.fk_user_modif,';
        $sql .= ' co.label as country, co.code as country_code,';
        $sql .= ' d.nom as state, d.code_departement as state_code,';
        $sql .= ' u.rowid as user_id, u.login as user_login,';
        $sql .= ' s.nom as socname, s.address as socaddress, s.zip as soccp, s.town as soccity, s.default_lang as socdefault_lang';
        $sql .= ' FROM llx_socpeople as c';
        $sql .= ' LEFT JOIN llx_socpeople_extrafields as cef ON cef.fk_object=c.rowid';
        $sql .= ' LEFT JOIN llx_c_country as co ON c.fk_pays = co.rowid';
        $sql .= ' LEFT JOIN llx_c_departements as d ON c.fk_departement = d.rowid';
        $sql .= ' LEFT JOIN llx_user as u ON c.rowid = u.fk_socpeople';
        $sql .= ' LEFT JOIN llx_societe as s ON c.fk_soc = s.rowid';
        $sql .= ' LEFT JOIN llx_c_stcommcontact as st ON c.fk_stcommcontact = st.id';
        if ($rowid) {
            $sql .= ' WHERE c.rowid = ' . ((int) $rowid);
        } else {
            $sql .= ' WHERE c.entity IN (' . $this->config->getEntity('contact') . ')';
            if ($refExt !== '') {
                $sql .= ' AND c.ref_ext = ' . $this->db->quote($refExt);
            }
            if ($email !== '') {
                $sql .= ' AND c.email = ' . $this->db->quote($email);
            }
            if ($socid) {
                $sql .= ' AND c.fk_soc = ' . ((int) $socid);
            }
        }

        $rows = $this->db->fetchAllAssociative($sql);
        $num = count($rows);
        if ($num > 1) {
            $this->error = 'Fetch found several records. Rename one of contact to avoid duplicate.';

            return 2;
        }
        if ($num === 0) {
            $this->error = 'ErrorRecordNotFound';

            return 0;
        }

        $obj = $rows[0];

        $c->id = (int) $obj['rowid'];
        $c->entity = (int) $obj['entity'];
        $c->ref = (int) $obj['rowid'];
        $c->ref_ext = $obj['ref_ext'];

        $c->civility_code = $obj['civility_code'];
        // No translation layer: upstream falls back to the raw civility code.
        $c->civility = $obj['civility_code'] ? $obj['civility_code'] : '';

        $c->name_alias = $obj['name_alias'];
        $c->lastname = $obj['lastname'];
        $c->firstname = $obj['firstname'];
        $c->address = $obj['address'];
        $c->zip = $obj['zip'];
        $c->town = $obj['town'];

        $c->date_creation = $this->jdate($obj['date_creation']);
        $c->date_modification = $this->jdate($obj['date_modification']);
        $c->user_creation_id = $obj['fk_user_creat'] === null ? null : (int) $obj['fk_user_creat'];
        $c->user_modification_id = $obj['fk_user_modif'] === null ? null : (int) $obj['fk_user_modif'];

        $c->state_id = $obj['state_id'] === null ? null : (int) $obj['state_id'];
        $c->state_code = $obj['state_code'];
        $c->state = $obj['state'];

        $c->country_id = $obj['country_id'] === null ? null : (int) $obj['country_id'];
        $c->country_code = $obj['country_id'] ? $obj['country_code'] : '';
        $c->country = $obj['country_id'] ? $obj['country'] : '';

        $c->fk_soc = $obj['fk_soc'] === null ? null : (int) $obj['fk_soc'];
        $c->socid = $obj['fk_soc'] === null ? null : (int) $obj['fk_soc'];
        $c->socname = $obj['socname'];
        $c->poste = $obj['poste'];
        $c->status = (int) $obj['status'];
        $c->statut = (int) $obj['status']; // deprecated

        $c->fk_prospectlevel = $obj['fk_prospectlevel'];

        // No translation layer: upstream falls back to the c_stcommcontact libelle.
        $c->stcomm_id = $obj['fk_stcommcontact'] === null ? null : (int) $obj['fk_stcommcontact'];
        $c->statut_commercial = $obj['stcomm'];
        $c->stcomm_picto = $obj['stcomm_picto'];

        $c->phone_pro = trim((string) $obj['phone']);
        $c->fax = trim((string) $obj['fax']);
        $c->phone_perso = trim((string) $obj['phone_perso']);
        $c->phone_mobile = trim((string) $obj['phone_mobile']);

        $c->email = $obj['email'];
        $c->socialnetworks = $obj['socialnetworks'] ? (array) json_decode((string) $obj['socialnetworks'], true) : [];
        $c->photo = $obj['photo'];
        $c->priv = $obj['priv'] === null ? null : (int) $obj['priv'];
        $c->mail = $obj['email'];

        $c->birthday = $this->jdate($obj['birthday']);
        $c->note = $obj['note_private']; // deprecated
        $c->note_private = $obj['note_private'];
        $c->note_public = $obj['note_public'];
        $c->default_lang = $obj['default_lang'];
        $c->user_id = $obj['user_id'] === null ? null : (int) $obj['user_id'];
        $c->user_login = $obj['user_login'];
        $c->canvas = $obj['canvas'];

        $c->import_key = $obj['import_key'];

        // Define gender according to civility
        $this->setGenderFromCivility($c);

        // fetch_optionals: extra fields
        $c->array_options = $this->fetchExtrafields('socpeople', $c->id);

        return $c->id;
    }

    /**
     * Port of Contact::create(). Returns >0 new id, <0 error.
     */
    public function create(Contact $c): int
    {
        $error = 0;
        $now = time();

        if (empty($c->date_creation)) {
            $c->date_creation = $now;
        }

        // Clean parameters
        $c->name_alias = trim((string) $c->name_alias);
        $c->lastname = $c->lastname ? trim((string) $c->lastname) : trim((string) $c->name);
        $c->firstname = trim((string) $c->firstname);
        $this->setUpperOrLowerCase($c);
        if (empty($c->socid)) {
            $c->socid = 0;
        }
        if (empty($c->priv)) {
            $c->priv = 0;
        }
        if (!empty($c->statut) && empty($c->status)) {
            $c->status = 1;
        }
        if (empty($c->status)) {
            $c->status = 0;
            $c->statut = 0;
        }

        $c->entity = $this->config->setEntity($c);

        $userId = $this->config->apiUserId();

        $this->db->beginTransaction();
        try {
            $this->db->insert('llx_socpeople', [
                'datec' => $this->idate($now),
                'fk_soc' => $c->socid > 0 ? (int) $c->socid : null,
                'name_alias' => $c->name_alias,
                'lastname' => $c->lastname,
                'firstname' => $c->firstname,
                'fk_user_creat' => $userId > 0 ? $userId : null,
                'priv' => (int) $c->priv,
                'fk_stcommcontact' => 0,
                'statut' => (int) $c->status,
                'canvas' => !empty($c->canvas) ? $c->canvas : null,
                'entity' => (int) $c->entity,
                'ref_ext' => (string) ($c->ref_ext ?? ''),
                'import_key' => !empty($c->import_key) ? $c->import_key : null,
                'ip' => !empty($c->ip) ? $c->ip : null,
            ]);
            $c->id = (int) $this->db->lastInsertId();

            $result = $this->update($c, $c->id, 1, 'add'); // includes updateRoles()
            if ($result < 0) {
                $error++;
            }

            if (!$error) {
                $result = $this->updatePerso($c, $c->id, 1);
                if ($result < 0) {
                    $error++;
                }
            }

            if ($error) {
                $this->db->rollBack();

                return -2;
            }
            $this->db->commit();

            return $c->id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->error = $e->getMessage();

            return -1;
        }
    }

    /**
     * Port of Contact::update(). Returns >0 OK, <0 error.
     * The linked-llx_user sync block is skipped: llx_user is a stub here.
     */
    public function update(Contact $c, int $id, int $notrigger = 0, string $action = 'update'): int
    {
        $error = 0;

        if (empty($c->country_id) && !empty($c->country_code)) {
            // upstream quirk: getCountry(.., '3') returns the raw DB string,
            // so is_int() fails and country_id ends up 0 — replicated verbatim.
            $countryId = $this->getCountry((string) $c->country_code);
            $c->country_id = is_int($countryId) ? $countryId : 0;
        }

        $c->id = $id;

        $c->entity = (isset($c->entity) && is_numeric($c->entity)) ? (int) $c->entity : $this->config->entity();

        // Clean parameters
        $c->ref_ext = empty($c->ref_ext) ? '' : trim((string) $c->ref_ext);
        $c->name_alias = trim((string) $c->name_alias);
        $c->lastname = $c->lastname ? trim((string) $c->lastname) : trim((string) $c->name);
        $c->firstname = trim((string) $c->firstname);
        $c->email = trim((string) ($c->email ?? ''));
        $c->phone_pro = trim((string) $c->phone_pro);
        $c->phone_perso = trim((string) $c->phone_perso);
        $c->phone_mobile = trim((string) $c->phone_mobile);
        $c->photo = trim((string) $c->photo);
        $c->fax = trim((string) $c->fax);
        $c->zip = empty($c->zip) ? '' : trim((string) $c->zip);
        $c->town = empty($c->town) ? '' : trim((string) $c->town);
        $c->country_id = (empty($c->country_id) || $c->country_id < 0) ? 0 : (int) $c->country_id;
        if (!empty($c->statut) && empty($c->status)) {
            $c->status = 1;
        }
        if (empty($c->status)) {
            $c->status = 0;
            $c->statut = 0;
        }
        if (empty($c->civility_code) && !is_numeric($c->civility_id)) {
            $c->civility_code = $c->civility_id; // For backward compatibility
        }
        $this->setUpperOrLowerCase($c);

        $userId = $this->config->apiUserId();

        $setParts = [];
        if ($c->socid > 0) {
            $setParts[] = 'fk_soc = ' . ((int) $c->socid);
        } elseif ($c->socid == -1) {
            $setParts[] = 'fk_soc = NULL';
        }
        $setParts[] = 'civility = ' . $this->db->quote((string) $c->civility_code);
        $setParts[] = 'name_alias = ' . $this->db->quote($c->name_alias);
        $setParts[] = 'lastname = ' . $this->db->quote($c->lastname);
        $setParts[] = 'firstname = ' . $this->db->quote($c->firstname);
        $setParts[] = 'address = ' . $this->db->quote((string) $c->address);
        $setParts[] = 'zip = ' . $this->db->quote($c->zip);
        $setParts[] = 'town = ' . $this->db->quote($c->town);
        $setParts[] = 'ref_ext = ' . (!empty($c->ref_ext) ? $this->db->quote($c->ref_ext) : 'NULL');
        $setParts[] = 'fk_pays = ' . ($c->country_id > 0 ? ((int) $c->country_id) : 'NULL');
        $setParts[] = 'fk_departement = ' . (($c->state_id ?? 0) > 0 ? ((int) $c->state_id) : 'NULL');
        $setParts[] = 'poste = ' . $this->db->quote((string) $c->poste);
        $setParts[] = 'fax = ' . $this->db->quote($c->fax);
        $setParts[] = 'email = ' . $this->db->quote($c->email);
        $setParts[] = 'socialnetworks = ' . $this->db->quote(json_encode($c->socialnetworks));
        $setParts[] = 'photo = ' . $this->db->quote($c->photo);
        $setParts[] = 'birthday = ' . ($c->birthday ? $this->db->quote($this->idate($c->birthday)) : 'null');
        $setParts[] = 'note_private = ' . (isset($c->note_private) ? $this->db->quote($c->note_private) : 'NULL');
        $setParts[] = 'note_public = ' . (isset($c->note_public) ? $this->db->quote($c->note_public) : 'NULL');
        $setParts[] = 'phone = ' . (isset($c->phone_pro) ? $this->db->quote($c->phone_pro) : 'NULL');
        $setParts[] = 'phone_perso = ' . (isset($c->phone_perso) ? $this->db->quote($c->phone_perso) : 'NULL');
        $setParts[] = 'phone_mobile = ' . (isset($c->phone_mobile) ? $this->db->quote($c->phone_mobile) : 'NULL');
        $setParts[] = 'priv = ' . ((int) $c->priv);
        $setParts[] = 'fk_prospectlevel = ' . $this->db->quote((string) $c->fk_prospectlevel);
        if (isset($c->stcomm_id)) {
            $setParts[] = 'fk_stcommcontact = ' . ($c->stcomm_id > 0 || $c->stcomm_id == -1 ? ((int) $c->stcomm_id) : '0');
        }
        $setParts[] = 'statut = ' . ((int) $c->status);
        $setParts[] = 'fk_user_modif = ' . ($userId > 0 ? $this->db->quote((string) $userId) : 'NULL');
        $setParts[] = 'default_lang = ' . ($c->default_lang ? $this->db->quote($c->default_lang) : 'NULL');
        $setParts[] = 'entity = ' . ((int) $c->entity);

        $sql = 'UPDATE llx_socpeople SET ' . implode("\n, ", $setParts) . ' WHERE rowid = ' . ((int) $id);

        $this->db->beginTransaction();
        try {
            $this->db->executeStatement($sql);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage() . ' sql=' . $sql;
            $this->db->rollBack();

            return -1;
        }

        unset($c->country_code, $c->country, $c->state_code, $c->state);

        // Actions on extra fields (insertExtraFields — delete + reinsert)
        $result = $this->saveExtrafields('socpeople', $id, $c->array_options);
        if ($result < 0) {
            $error++;
        }

        if (!$error) {
            $result = $this->updateRoles($c);
            if ($result < 0) {
                $error++;
            }
        }

        // upstream syncs the linked llx_user row here when $c->user_id > 0;
        // llx_user is a stub in this service (no address/phone columns), skipped.

        if (!$error) {
            $this->db->commit();

            return 1;
        }
        $this->db->rollBack();

        return -$error;
    }

    /**
     * Port of Contact::update_perso() — birthday + photo + birthday alert.
     */
    public function updatePerso(Contact $c, int $id, int $notrigger = 0): int
    {
        $error = 0;
        $userId = $this->config->apiUserId();

        $this->db->beginTransaction();
        try {
            $sql = 'UPDATE llx_socpeople SET';
            $sql .= ' birthday = ' . ($c->birthday ? $this->db->quote($this->idate($c->birthday)) : 'null');
            $sql .= ', photo = ' . ($c->photo ? $this->db->quote($c->photo) : 'null');
            if ($userId > 0) {
                $sql .= ', fk_user_modif = ' . ((int) $userId);
            }
            $sql .= ' WHERE rowid = ' . ((int) $id);
            $this->db->executeStatement($sql);

            if ($userId > 0) {
                if (!empty($c->birthday_alert)) {
                    $exists = $this->db->fetchOne(
                        'SELECT rowid FROM llx_user_alert WHERE type = 1 AND fk_contact = ' . ((int) $id) . ' AND fk_user = ' . ((int) $userId)
                    );
                    if ($exists === false) {
                        $this->db->insert('llx_user_alert', [
                            'type' => 1,
                            'fk_contact' => $id,
                            'fk_user' => $userId,
                        ]);
                    }
                } else {
                    $this->db->executeStatement(
                        'DELETE FROM llx_user_alert WHERE type = 1 AND fk_contact = ' . ((int) $id) . ' AND fk_user = ' . ((int) $userId)
                    );
                }
            }

            $this->db->commit();

            return 1;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->db->rollBack();

            return -1;
        }
    }

    /**
     * Port of Contact::delete() — cascade cleanup then delete socpeople row.
     * Returns 1 on success, <0 on error.
     */
    public function delete(Contact $c): int
    {
        $error = 0;
        $id = (int) $c->id;

        $this->db->beginTransaction();
        try {
            // Remove roles on objects the contact is linked to (source='external')
            $ecRows = $this->db->fetchFirstColumn(
                'SELECT ec.rowid FROM llx_element_contact ec, llx_c_type_contact tc'
                . ' WHERE ec.fk_socpeople = ' . $id
                . ' AND ec.fk_c_type_contact = tc.rowid'
                . " AND tc.source = 'external'"
            );
            foreach ($ecRows as $ecRowid) {
                $this->db->executeStatement('DELETE FROM llx_element_contact WHERE rowid = ' . ((int) $ecRowid));
            }

            // Remove Roles
            $this->db->executeStatement('DELETE FROM llx_societe_contacts WHERE fk_socpeople = ' . $id);

            // Remove Notifications
            $this->db->executeStatement('DELETE FROM llx_notify_def WHERE fk_contact = ' . $id);

            // Remove category links
            $this->db->executeStatement('DELETE FROM llx_categorie_contact WHERE fk_socpeople = ' . $id);

            // Remove contact
            $this->db->executeStatement('DELETE FROM llx_socpeople WHERE rowid = ' . $id);

            // Remove extrafields (deleteExtraFields)
            $this->db->executeStatement('DELETE FROM llx_socpeople_extrafields WHERE fk_object = ' . $id);

            $this->db->commit();

            return 1;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errors[] = $e->getMessage();
            $this->db->rollBack();

            return -1;
        }
    }

    /**
     * Port of Contact::fetchRoles() — fills $c->roles keyed by
     * llx_societe_contacts.rowid. Returns number of roles or <0.
     *
     * Note: upstream labels are
     * $langs->trans('ContactDefault_<element>').' - '.trans(...) falling
     * back to the trans key / c_type_contact.libelle — reproduced here as
     * 'ContactDefault_<element> - <libelle>'.
     */
    public function fetchRoles(Contact $c): int
    {
        $sql = 'SELECT tc.rowid, tc.element, tc.source, tc.code, tc.libelle as label, sc.rowid as contactroleid, sc.fk_soc as socid';
        $sql .= ' FROM llx_societe_contacts as sc, llx_c_type_contact as tc';
        $sql .= ' WHERE tc.rowid = sc.fk_c_type_contact';
        $sql .= " AND tc.source = 'external' AND tc.active = 1";
        $sql .= ' AND sc.fk_socpeople = ' . ((int) $c->id);
        $sql .= ' AND sc.entity IN (' . $this->config->getEntity('societe') . ')';

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errors[] = $e->getMessage();

            return -1;
        }

        $c->roles = [];
        foreach ($rows as $obj) {
            // No translation layer: upstream falls back to 'ContactDefault_<element>'
            // trans key / c_type_contact libelle when no translation exists.
            $labelElement = 'ContactDefault_' . $obj['element'];
            $transkey = 'TypeContact_' . $obj['element'] . '_' . $obj['source'] . '_' . $obj['code'];
            $c->roles[(int) $obj['contactroleid']] = [
                'id' => (int) $obj['rowid'],
                'socid' => (int) $obj['socid'],
                'element' => $obj['element'],
                'source' => $obj['source'],
                'code' => $obj['code'],
                'label' => $labelElement . ' - ' . $obj['label'],
            ];
        }

        return count($rows);
    }

    /**
     * Port of Contact::updateRoles() — delete + reinsert llx_societe_contacts
     * rows. No-op when $c->roles was never assigned (declared null, like
     * upstream's unset property).
     */
    public function updateRoles(Contact $c): int
    {
        if (!isset($c->roles)) {
            return 0; // Avoid losing roles when property not set
        }
        $c->id = (int) $c->id;

        try {
            $this->db->executeStatement(
                'DELETE FROM llx_societe_contacts WHERE fk_socpeople = ' . $c->id
                . ' AND entity IN (' . $this->config->getEntity('contact') . ')'
            );

            if (count($c->roles) > 0) {
                foreach ($c->roles as $valRoles) {
                    if (empty($valRoles)) {
                        continue;
                    }
                    if (is_array($valRoles)) {
                        $idrole = $valRoles['id'] ?? 0;
                        $socid = $valRoles['socid'] ?? 0;
                    } else {
                        $idrole = $valRoles;
                        $socid = $c->socid;
                    }
                    if ($socid > 0) {
                        $this->db->executeStatement(
                            'INSERT INTO llx_societe_contacts (entity, date_creation, fk_soc, fk_c_type_contact, fk_socpeople)'
                            . ' VALUES (' . $this->config->entity() . ", '" . $this->idate(time()) . "', "
                            . ((int) $socid) . ', ' . ((int) $idrole) . ', ' . $c->id . ')'
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage();

            return -1;
        }

        return 1;
    }

    /**
     * Port of Contact::load_ref_elements() — counts element_contact links per
     * element type into ref_facturation/ref_contrat/ref_commande/ref_propal.
     */
    public function loadRefElements(Contact $c): int
    {
        $sql = 'SELECT tc.element, count(ec.rowid) as nb';
        $sql .= ' FROM llx_element_contact as ec, llx_c_type_contact as tc';
        $sql .= ' WHERE ec.fk_c_type_contact = tc.rowid';
        $sql .= ' AND ec.fk_socpeople = ' . ((int) $c->id);
        $sql .= " AND tc.source = 'external'";
        $sql .= ' GROUP BY tc.element';

        try {
            $rows = $this->db->fetchAllAssociative($sql);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        foreach ($rows as $obj) {
            match ($obj['element']) {
                'facture' => $c->ref_facturation = (int) $obj['nb'],
                'contrat' => $c->ref_contrat = (int) $obj['nb'],
                'commande' => $c->ref_commande = (int) $obj['nb'],
                'propal' => $c->ref_propal = (int) $obj['nb'],
                default => null,
            };
        }

        return 0;
    }

    /**
     * Port of Contact::initAsSpecimen() — returned by GET 0.
     */
    public function initAsSpecimen(Contact $c): int
    {
        $socid = 0;
        $row = $this->db->fetchAssociative('SELECT rowid FROM llx_societe ORDER BY rowid LIMIT 1');
        if ($row !== false) {
            $socid = (int) $row['rowid'];
        }

        $c->id = 0;
        $c->entity = 1;
        $c->specimen = 1;
        $c->lastname = 'DOLIBARR';
        $c->firstname = 'SPECIMEN';
        $c->address = '21 jump street';
        $c->zip = '99999';
        $c->town = 'MyTown';
        $c->country_id = 1;
        $c->country_code = 'FR';
        $c->country = 'France';
        $c->email = 'specimen@specimen.com';
        $c->socialnetworks = [
            'skype' => 'tom.hanson',
            'twitter' => 'tomhanson',
            'linkedin' => 'tomhanson',
        ];
        $c->phone_pro = '0909090901';
        $c->phone_perso = '0909090902';
        $c->phone_mobile = '0909090903';
        $c->fax = '0909090909';

        $c->note_public = 'This is a comment (public)';
        $c->note_private = 'This is a comment (private)';

        $c->socid = $socid;
        $c->status = 1;

        return 1;
    }

    /**
     * Port of Contact::setNoEmail() — insert/delete llx_mailing_unsubscribe.
     */
    public function setNoEmail(Contact $c, int $noEmail): int
    {
        if (!$c->email) {
            return 0;
        }

        $entity = $this->config->getEntity('mailing', 0);
        try {
            if ($noEmail) {
                $nb = (int) $this->db->fetchOne(
                    'SELECT COUNT(rowid) as nb FROM llx_mailing_unsubscribe WHERE entity IN (' . $entity . ') AND email = ' . $this->db->quote($c->email)
                );
                if (empty($nb)) {
                    $this->db->executeStatement(
                        "INSERT INTO llx_mailing_unsubscribe (email, entity, date_creat) VALUES (" . $this->db->quote($c->email) . ', ' . $entity . ", '" . $this->idate(time()) . "')"
                    );
                }
            } else {
                $this->db->executeStatement(
                    'DELETE FROM llx_mailing_unsubscribe WHERE email = ' . $this->db->quote($c->email) . ' AND entity IN (' . $entity . ')'
                );
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errors[] = $e->getMessage();

            return -1;
        }

        $c->no_email = $noEmail;

        return 1;
    }

    /**
     * Port of Contact::getNoEmail() — sets $c->no_email from the
     * mailing_unsubscribe table.
     */
    public function getNoEmail(Contact $c): int
    {
        if (!$c->email) {
            return 0;
        }
        try {
            $nb = (int) $this->db->fetchOne(
                'SELECT COUNT(rowid) as nb FROM llx_mailing_unsubscribe WHERE entity IN (' . $this->config->getEntity('mailing') . ') AND email = ' . $this->db->quote($c->email)
            );
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->errors[] = $e->getMessage();

            return -1;
        }
        $c->no_email = $nb;

        return 1;
    }

    /**
     * Port of Contact::setGenderFromCivility().
     */
    public function setGenderFromCivility(Contact $c): void
    {
        unset($c->gender);

        if (in_array($c->civility_id, ['MR'], true) || in_array($c->civility_code, ['MR'], true)) {
            $c->gender = 'man';
        } elseif (in_array($c->civility_id, ['MME', 'MLE'], true) || in_array($c->civility_code, ['MME', 'MLE'], true)) {
            $c->gender = 'woman';
        }
    }

    /**
     * Port of CommonPeople::setUpperOrLowerCase() — config-driven case
     * normalisation; email is always lowercased.
     */
    public function setUpperOrLowerCase(Contact $c): void
    {
        if ($this->config->getString('MAIN_TE_PRIVATE_FIRST_AND_LASTNAME_TO_UPPER')) {
            $c->lastname = mb_convert_case(mb_strtolower((string) $c->lastname), MB_CASE_TITLE, 'UTF-8');
            $c->firstname = mb_convert_case(mb_strtolower((string) $c->firstname), MB_CASE_TITLE, 'UTF-8');
            if (!empty($c->firstname)) {
                $c->lastname = mb_strtoupper((string) $c->lastname, 'UTF-8');
            }
            $c->name_alias = isset($c->name_alias) ? mb_convert_case(mb_strtolower((string) $c->name_alias), MB_CASE_TITLE, 'UTF-8') : '';
        }
        if ($this->config->getString('MAIN_FIRST_TO_UPPER')) {
            $c->lastname = mb_convert_case(mb_strtolower((string) $c->lastname), MB_CASE_TITLE, 'UTF-8');
            $c->firstname = mb_convert_case(mb_strtolower((string) $c->firstname), MB_CASE_TITLE, 'UTF-8');
            $c->name = mb_convert_case(mb_strtolower((string) $c->name), MB_CASE_TITLE, 'UTF-8');
            $c->name_alias = isset($c->name_alias) ? mb_convert_case(mb_strtolower((string) $c->name_alias), MB_CASE_TITLE, 'UTF-8') : '';
        }
        if ($this->config->getString('MAIN_ALL_TO_UPPER')) {
            $c->lastname = mb_strtoupper((string) $c->lastname, 'UTF-8');
            $c->name = mb_strtoupper((string) $c->name, 'UTF-8');
            $c->name_alias = mb_strtoupper((string) $c->name_alias, 'UTF-8');
        }
        if ($this->config->getString('MAIN_ALL_TOWN_TO_UPPER')) {
            $c->address = mb_strtoupper((string) ($c->address ?? ''), 'UTF-8');
            $c->town = mb_strtoupper((string) ($c->town ?? ''), 'UTF-8');
        }
        if (!empty($c->email)) {
            $c->email = mb_strtolower((string) $c->email, 'UTF-8');
        }
    }

    // ==================================================================
    //  _checkAccessToResource / checkUserAccessToObject
    // ==================================================================

    /**
     * Port of DolibarrApi::_checkAccessToResource() restricted to the
     * resources used by this API: 'contact' (checkparentsoc on socpeople),
     * 'societe' (checksoc) and 'category' (entity-only check on llx_categorie).
     *
     * $tableAndShare resolves like upstream: 'socpeople&societe' means
     * table llx_socpeople with entity shared through getEntity('societe');
     * empty falls back to the feature name ('contact' → llx_contact, which
     * does not exist — upstream then fails the check for the sql-driven
     * branches, replicated here).
     *
     * The API user is emulated by env config: DOLIBARR_API_SOCID > 0 = external
     * user; DOLIBARR_API_RIGHT_SOCIETE_CLIENT_VOIR=false = restricted internal
     * user (sees only commerciaux-linked thirdparties); multicompany module =
     * entity check for everyone.
     */
    public function checkAccessToResource(string $resource, int|string $objectId, string $tableAndShare = ''): bool
    {
        $features = preg_split('/[&|]/', $resource) ?: [$resource];
        foreach ($features as $feature) {
            $feature = match ($feature) {
                'category' => 'categorie',
                'member' => 'adherent',
                'project' => 'projet',
                default => $feature,
            };

            $dbtablename = $feature;
            $sharedelement = $feature;
            if ($tableAndShare !== '' && str_contains($tableAndShare, '&')) {
                [$dbtablename, $sharedelement] = explode('&', $tableAndShare, 2);
            } elseif ($tableAndShare !== '') {
                $dbtablename = $tableAndShare;
                $sharedelement = $tableAndShare;
            }

            $allowed = match ($feature) {
                'contact' => $this->checkParentSoc((int) $objectId, 'llx_' . $dbtablename, $sharedelement),
                'societe' => $this->checkSoc((int) $objectId, $sharedelement),
                'categorie' => $this->checkEntityOnly('llx_' . $dbtablename, $sharedelement, (int) $objectId),
                default => true,
            };
            if (!$allowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * checkparentsoc rule: entity + link to thirdparty on fk_soc; the link is
     * allowed empty for restricted internal users. A missing table (e.g.
     * llx_contact when the API passes no dbtablename) fails the sql-driven
     * branches like upstream.
     */
    private function checkParentSoc(int $objectId, string $table, string $sharedelement): bool
    {
        if (!$objectId) {
            return true;
        }
        $userSocid = $this->config->apiSocId();
        if ($userSocid > 0) {
            $sql = 'SELECT COUNT(dbt.rowid) as nb FROM ' . $table . ' as dbt'
                . ' WHERE dbt.rowid = ' . $objectId
                . ' AND dbt.entity IN (' . $this->config->getEntity($sharedelement) . ')'
                . ' AND dbt.fk_soc = ' . $userSocid;
        } elseif ($this->config->hasRight('societe', 'lire') && !$this->config->hasRight('societe', 'client', 'voir')) {
            $sql = 'SELECT COUNT(dbt.rowid) as nb FROM ' . $table . ' as dbt'
                . ' LEFT JOIN llx_societe_commerciaux as sc ON dbt.fk_soc = sc.fk_soc AND sc.fk_user = ' . $this->config->apiUserId()
                . ' WHERE dbt.rowid = ' . $objectId
                . ' AND (dbt.fk_soc IS NULL OR sc.fk_soc IS NOT NULL)'
                . ' AND dbt.entity IN (' . $this->config->getEntity($sharedelement) . ')';
        } elseif ($this->config->isModEnabled('multicompany')) {
            $sql = 'SELECT COUNT(dbt.rowid) as nb FROM ' . $table . ' as dbt'
                . ' WHERE dbt.rowid = ' . $objectId
                . ' AND dbt.entity IN (' . $this->config->getEntity($sharedelement) . ')';
        } else {
            return true; // internal full-rights user: upstream runs no query
        }

        try {
            $nb = (int) $this->db->fetchOne($sql);
        } catch (\Throwable) {
            return false; // "Bad forged sql" path — upstream returns false
        }

        return $nb >= 1;
    }

    /** checksoc rule for 'societe'. */
    private function checkSoc(int $objectId, string $sharedelement): bool
    {
        if (!$objectId) {
            return true;
        }
        $userSocid = $this->config->apiSocId();
        if ($userSocid > 0) {
            return $userSocid === $objectId;
        }
        if ($this->config->hasRight('societe', 'lire') && !$this->config->hasRight('societe', 'client', 'voir')) {
            $nb = (int) $this->db->fetchOne(
                'SELECT COUNT(sc.fk_soc) as nb FROM (llx_societe_commerciaux as sc, llx_societe as s)'
                . ' WHERE sc.fk_soc = ' . $objectId
                . ' AND sc.fk_user = ' . $this->config->apiUserId()
                . ' AND sc.fk_soc = s.rowid'
                . ' AND s.entity IN (' . $this->config->getEntity($sharedelement) . ')'
            );

            return $nb >= 1;
        }
        if ($this->config->isModEnabled('multicompany')) {
            return $this->checkEntityOnly('llx_societe', $sharedelement, $objectId);
        }

        return true;
    }

    /** Entity-only rule (check list: 'categorie', ...). */
    private function checkEntityOnly(string $table, string $entityElement, int $objectId): bool
    {
        if (!$objectId) {
            return true;
        }
        try {
            $nb = (int) $this->db->fetchOne(
                'SELECT COUNT(dbt.rowid) as nb FROM ' . $table . ' as dbt'
                . ' WHERE dbt.rowid = ' . $objectId
                . ' AND dbt.entity IN (' . $this->config->getEntity($entityElement) . ')'
            );
        } catch (\Throwable) {
            return false;
        }

        return $nb >= 1;
    }

    // ==================================================================
    //  extrafields
    // ==================================================================

    /**
     * fetch_optionals(): extra fields stored as array_options['options_xxx'].
     *
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

        $options = [];
        foreach ($row as $key => $value) {
            $options['options_' . $key] = $value;
        }

        return $options;
    }

    /**
     * insertExtraFields(): delete the extrafields row then reinsert the
     * options_* keys (unknown columns are skipped, like upstream).
     */
    public function saveExtrafields(string $tableElement, int $fkObject, array $options): int
    {
        $table = 'llx_' . $tableElement . '_extrafields';
        if (!$this->tableExists($table)) {
            return 0;
        }

        try {
            $this->db->executeStatement("DELETE FROM $table WHERE fk_object = " . (int) $fkObject);

            $columns = array_flip($this->extraColumns($table));
            $data = [];
            foreach ($options as $key => $value) {
                // upstream keys are 'options_<column>' — strip the prefix
                $col = str_starts_with($key, 'options_') ? substr($key, 8) : $key;
                if (isset($columns[$col]) && !is_array($value)) {
                    $data[$col] = $value;
                }
            }
            if ($data !== []) {
                $data['fk_object'] = $fkObject;
                $this->db->insert($table, $data);
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return -1;
        }

        return 1;
    }

    /** @return string[] */
    private function extraColumns(string $table): array
    {
        $cols = array_keys($this->db->createSchemaManager()->listTableColumns($table));

        return array_values(array_diff($cols, ['rowid', 'tms', 'fk_object', 'import_key']));
    }

    public function tableExists(string $table): bool
    {
        try {
            return $this->db->createSchemaManager()->tablesExist([$table]);
        } catch (\Throwable) {
            return false;
        }
    }

    // ==================================================================
    //  helpers
    // ==================================================================

    /**
     * Port of getCountry($searchkey, '3'): returns the c_country rowid for a
     * numeric id or a 2-letter code, '' when not found.
     * Returns the raw DB value (string), like upstream fetch_object.
     */
    public function getCountry(string $searchkey): int|string
    {
        if ($searchkey === '') {
            return '';
        }
        if (is_numeric($searchkey)) {
            $row = $this->db->fetchOne('SELECT rowid FROM llx_c_country WHERE rowid = ' . ((int) $searchkey));
        } else {
            $row = $this->db->fetchOne('SELECT rowid FROM llx_c_country WHERE code = ' . $this->db->quote($searchkey));
        }

        return $row === false ? '' : $row;
    }

    /** Port of $db->jdate() — DB datetime string to unix timestamp. */
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

    /**
     * Port of DoliDB::idate() — accepts a unix timestamp or a date string
     * ('Y-m-d', 'YmdHis', ...) parsed like dol_print_date (GMT).
     */
    private function idate(mixed $value, bool $dayOnly = false): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value) && strlen($value) > 6)) {
            $ts = (int) $value;
        } else {
            $ts = strtotime((string) $value);
            if ($ts === false) {
                return '';
            }
        }

        return $dayOnly ? date('Y-m-d', $ts) : date('Y-m-d H:i:s', $ts);
    }
}
