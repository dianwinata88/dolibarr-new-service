<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consolidated CRM schema port.
 *
 * DDL is copied verbatim from upstream Dolibarr install SQL
 * (htdocs/install/mysql/tables/*.sql + *.key.sql) so the physical schema —
 * column types, nullability, defaults, index names — matches the monolith.
 * Upstream FOREIGN KEY constraints are intentionally not created: Dolibarr
 * treats fk_* columns as soft references and most of them point at tables
 * owned by other slices of the monolith. utf8mb4/utf8mb4_unicode_ci is
 * declared explicitly (upstream relies on the server default).
 */
final class Version20260919155802 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Port Dolibarr CRM tables (societe, socpeople, categorie, c_* dictionaries, llx_user stub)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE llx_societe (rowid integer AUTO_INCREMENT PRIMARY KEY, nom varchar(128), name_alias varchar(128) NULL, entity integer DEFAULT 1 NOT NULL, ref_ext varchar(255), statut tinyint DEFAULT 0, parent integer, status tinyint DEFAULT 1, code_client varchar(24), code_fournisseur varchar(24), tp_payment_reference varchar(25), accountancy_code_customer_general varchar(32) DEFAULT NULL, code_compta varchar(32), accountancy_code_supplier_general varchar(32) DEFAULT NULL, code_compta_fournisseur varchar(32), address varchar(255), zip varchar(25), town varchar(50), fk_departement integer DEFAULT 0, fk_pays integer DEFAULT 0, geolat double(24,8) DEFAULT NULL, geolong double(24,8) DEFAULT NULL, geopoint point DEFAULT NULL, georesultcode varchar(16), phone varchar(30), phone_mobile varchar(30), fax varchar(30), url varchar(255), email varchar(128), fk_account integer DEFAULT 0, socialnetworks text DEFAULT NULL, fk_effectif integer DEFAULT 0, fk_typent integer DEFAULT NULL, fk_forme_juridique integer DEFAULT 0, birth date, fk_currency varchar(3), siren varchar(128), siret varchar(128), ape varchar(128), idprof4 varchar(128), idprof5 varchar(128), idprof6 varchar(128), euid varchar(64), tva_intra varchar(20), capital double(24,8) DEFAULT NULL, fk_stcomm integer DEFAULT 0 NOT NULL, note_private text, note_public text, model_pdf varchar(255), last_main_doc varchar(255), prefix_comm varchar(5), client tinyint DEFAULT 0, fournisseur tinyint DEFAULT 0, supplier_account varchar(32), fk_prospectlevel varchar(12), fk_incoterms integer, location_incoterms varchar(255), customer_bad tinyint DEFAULT 0, customer_rate real DEFAULT 0, supplier_rate real DEFAULT 0, remise_client real DEFAULT 0, remise_supplier real DEFAULT 0, mode_reglement integer, cond_reglement tinyint, deposit_percent varchar(63) DEFAULT NULL, transport_mode tinyint, mode_reglement_supplier tinyint, cond_reglement_supplier tinyint, transport_mode_supplier tinyint, fk_shipping_method integer, tva_assuj tinyint DEFAULT 1, vatexemptcode varchar(24), vat_reverse_charge tinyint DEFAULT 0, localtax1_assuj tinyint DEFAULT 0, localtax1_value double(7,4), localtax2_assuj tinyint DEFAULT 0, localtax2_value double(7,4), barcode varchar(180), fk_barcode_type integer NULL DEFAULT 0, price_level integer NULL, outstanding_limit double(24,8) DEFAULT NULL, order_min_amount double(24,8) DEFAULT NULL, supplier_order_min_amount double(24,8) DEFAULT NULL, default_lang varchar(6), logo varchar(255) DEFAULT NULL, logo_squarred varchar(255) DEFAULT NULL, canvas varchar(32) DEFAULT NULL, fk_warehouse integer DEFAULT NULL, webservices_url varchar(255), webservices_key varchar(128), accountancy_code_sell varchar(32), accountancy_code_buy varchar(32), datec datetime, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, fk_user_creat integer NULL, fk_user_modif integer, fk_multicurrency integer, multicurrency_code varchar(3), ip varchar(250), import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe ADD UNIQUE INDEX uk_societe_prefix_comm(prefix_comm, entity)');
        $this->addSql('ALTER TABLE llx_societe ADD UNIQUE INDEX uk_societe_code_client(code_client, entity)');
        $this->addSql('ALTER TABLE llx_societe ADD UNIQUE INDEX uk_societe_code_fournisseur(code_fournisseur, entity)');
        $this->addSql('ALTER TABLE llx_societe ADD UNIQUE INDEX uk_societe_barcode (barcode, fk_barcode_type, entity)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_nom(nom)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_user_creat(fk_user_creat)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_user_modif(fk_user_modif)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_stcomm(fk_stcomm)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_pays(fk_pays)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_account(fk_account)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_prospectlevel(fk_prospectlevel)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_typent(fk_typent)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_forme_juridique(fk_forme_juridique)');
        $this->addSql('ALTER TABLE llx_societe ADD INDEX idx_societe_shipping_method(fk_shipping_method)');
        $this->addSql('CREATE TABLE llx_societe_extrafields (rowid integer AUTO_INCREMENT PRIMARY KEY, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, fk_object integer NOT NULL, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_extrafields ADD UNIQUE INDEX uk_societe_extrafields (fk_object)');
        $this->addSql('CREATE TABLE llx_societe_commerciaux (rowid integer AUTO_INCREMENT PRIMARY KEY, fk_soc integer, fk_user integer, fk_c_type_contact_code varchar(32) NOT NULL DEFAULT \'SALESREPTHIRD\', import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_commerciaux ADD UNIQUE INDEX uk_societe_commerciaux_c_type_contact (fk_soc, fk_user, fk_c_type_contact_code)');
        $this->addSql('CREATE TABLE llx_societe_prices (rowid integer AUTO_INCREMENT PRIMARY KEY, fk_soc integer DEFAULT 0, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, datec datetime, fk_user_author integer, price_level tinyint DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_societe_remise (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, fk_soc integer NOT NULL, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, datec datetime, fk_user_author integer, remise_client double(7,4) DEFAULT 0 NOT NULL, note text) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_societe_remise_except (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, fk_soc integer NOT NULL, discount_type integer DEFAULT 0 NOT NULL, datec datetime, amount_ht double(24,8) NOT NULL, amount_tva double(24,8) DEFAULT 0 NOT NULL, amount_localtax1 double(24,8) DEFAULT 0 NOT NULL, amount_localtax2 double(24,8) DEFAULT 0 NOT NULL, amount_ttc double(24,8) DEFAULT 0 NOT NULL, tva_tx double(7,4) DEFAULT 0 NOT NULL, localtax1_tx double(7,4) DEFAULT 0 NOT NULL, localtax1_type varchar(10) NULL, localtax2_tx double(7,4) DEFAULT 0 NOT NULL, localtax2_type varchar(10) NULL, vat_src_code varchar(10) DEFAULT \'\', fk_user integer NOT NULL, fk_facture_line integer, fk_facture integer, fk_facture_source integer, fk_invoice_supplier_line integer, fk_invoice_supplier integer, fk_invoice_supplier_source integer, description text NOT NULL, multicurrency_code varchar(3) NULL, multicurrency_tx double(24,8) NULL, multicurrency_amount_ht double(24,8) DEFAULT 0 NOT NULL, multicurrency_amount_tva double(24,8) DEFAULT 0 NOT NULL, multicurrency_amount_ttc double(24,8) DEFAULT 0 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_remise_except ADD INDEX idx_societe_remise_except_fk_user (fk_user)');
        $this->addSql('ALTER TABLE llx_societe_remise_except ADD INDEX idx_societe_remise_except_fk_soc (fk_soc)');
        $this->addSql('ALTER TABLE llx_societe_remise_except ADD INDEX idx_societe_remise_except_fk_facture_line (fk_facture_line)');
        $this->addSql('ALTER TABLE llx_societe_remise_except ADD INDEX idx_societe_remise_except_fk_facture (fk_facture)');
        $this->addSql('ALTER TABLE llx_societe_remise_except ADD INDEX idx_societe_remise_except_fk_facture_source (fk_facture_source)');
        $this->addSql('ALTER TABLE llx_societe_remise_except ADD INDEX idx_societe_remise_except_discount_type (discount_type)');
        $this->addSql('CREATE TABLE llx_societe_rib (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, type varchar(32) DEFAULT \'ban\' NOT NULL, label varchar(180), fk_soc integer NOT NULL, datec datetime, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, bank varchar(255), code_banque varchar(128), code_guichet varchar(6), number varchar(255), cle_rib varchar(5), bic varchar(20), bic_intermediate varchar(11), iban_prefix varchar(100), cci varchar(100), domiciliation varchar(255), proprio varchar(60), owner_address varchar(255), default_rib smallint NOT NULL DEFAULT 0, state_id integer, fk_country integer, currency_code varchar(3), model_pdf varchar(255), last_main_doc varchar(255), rum varchar(32), date_rum date, frstrecur varchar(16) DEFAULT \'FRST\', last_four varchar(4), card_type varchar(255), cvn varchar(255), exp_date_month integer, exp_date_year integer, country_code varchar(10), approved integer DEFAULT 0, email varchar(255), ending_date date, max_total_amount_of_all_payments double(24,8), preapproval_key varchar(255), starting_date date, total_amount_of_all_payments double(24,8), stripe_card_ref varchar(128), stripe_account varchar(128), ext_payment_site varchar(128), extraparams varchar(255), date_signature datetime, online_sign_ip varchar(48), online_sign_name varchar(64), comment varchar(255), ipaddress varchar(68), status integer NOT NULL DEFAULT 1, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_rib ADD UNIQUE INDEX uk_societe_rib(entity, label, fk_soc)');
        $this->addSql('CREATE TABLE llx_societe_account (rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, entity integer DEFAULT 1, login varchar(128) NOT NULL, pass_encoding varchar(24), pass_crypted varchar(128), pass_temp varchar(128), fk_soc integer, fk_website integer, site varchar(128) NOT NULL, site_account varchar(128), key_account varchar(128), note_private text, date_last_login datetime, date_previous_login datetime, date_last_reset_password datetime, date_creation datetime NOT NULL, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, fk_user_creat integer NOT NULL, fk_user_modif integer, ip varchar(250), import_key varchar(14), status integer) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_account ADD INDEX idx_societe_account_rowid (rowid)');
        $this->addSql('ALTER TABLE llx_societe_account ADD INDEX idx_societe_account_login (login)');
        $this->addSql('ALTER TABLE llx_societe_account ADD INDEX idx_societe_account_status (status)');
        $this->addSql('ALTER TABLE llx_societe_account ADD INDEX idx_societe_account_fk_website (fk_website)');
        $this->addSql('ALTER TABLE llx_societe_account ADD INDEX idx_societe_account_fk_soc (fk_soc)');
        $this->addSql('ALTER TABLE llx_societe_account ADD UNIQUE INDEX uk_societe_account_login_website(entity, login, site, fk_website)');
        $this->addSql('ALTER TABLE llx_societe_account ADD UNIQUE INDEX uk_societe_account_key_account_soc(entity, fk_soc, key_account, site, fk_website)');
        $this->addSql('CREATE TABLE llx_societe_perentity (rowid integer AUTO_INCREMENT PRIMARY KEY, fk_soc integer, entity integer DEFAULT 1 NOT NULL, accountancy_code_customer_general varchar(32) DEFAULT NULL, accountancy_code_customer varchar(32), accountancy_code_supplier_general varchar(32) DEFAULT NULL, accountancy_code_supplier varchar(32), accountancy_code_sell varchar(32), accountancy_code_buy varchar(32), vat_reverse_charge tinyint DEFAULT 0, fk_account integer DEFAULT NULL, mode_reglement integer DEFAULT NULL, cond_reglement tinyint DEFAULT NULL, mode_reglement_supplier tinyint DEFAULT NULL, cond_reglement_supplier tinyint DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_perentity ADD INDEX idx_societe_perentity_fk_soc (fk_soc)');
        $this->addSql('ALTER TABLE llx_societe_perentity ADD UNIQUE INDEX uk_societe_perentity (fk_soc, entity)');
        $this->addSql('CREATE TABLE llx_socpeople (rowid integer AUTO_INCREMENT PRIMARY KEY, datec datetime, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, fk_soc integer, use_thirdparty_address smallint DEFAULT NULL, entity integer DEFAULT 1 NOT NULL, ref_ext varchar(255), name_alias varchar(255), fk_parent integer NULL, civility varchar(6), lastname varchar(50), firstname varchar(50), address varchar(255), zip varchar(25), town varchar(255), fk_departement integer, fk_pays integer DEFAULT 0, geolat double(24,8) DEFAULT NULL, geolong double(24,8) DEFAULT NULL, geopoint point DEFAULT NULL, georesultcode varchar(16), birthday date, poste varchar(255), phone varchar(30), phone_perso varchar(30), phone_mobile varchar(30), fax varchar(30), url varchar(255), email varchar(255), socialnetworks text DEFAULT NULL, photo varchar(255), no_email smallint NOT NULL DEFAULT 0, priv smallint NOT NULL DEFAULT 0, fk_prospectlevel varchar(12), fk_stcommcontact integer DEFAULT 0 NOT NULL, fk_user_creat integer DEFAULT 0, fk_user_modif integer, note_private text, note_public text, default_lang varchar(6), canvas varchar(32), import_key varchar(14), statut tinyint DEFAULT 1 NOT NULL, ip varchar(250)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_socpeople ADD INDEX idx_socpeople_fk_soc (fk_soc)');
        $this->addSql('ALTER TABLE llx_socpeople ADD INDEX idx_socpeople_fk_user_creat (fk_user_creat)');
        $this->addSql('ALTER TABLE llx_socpeople ADD INDEX idx_socpeople_lastname (lastname)');
        $this->addSql('CREATE TABLE llx_socpeople_extrafields (rowid integer AUTO_INCREMENT PRIMARY KEY, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, fk_object integer NOT NULL, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_socpeople_extrafields ADD UNIQUE INDEX uk_socpeople_extrafields (fk_object)');
        $this->addSql('CREATE TABLE llx_societe_contacts (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, date_creation datetime NOT NULL, fk_soc integer NOT NULL, fk_c_type_contact int NOT NULL, fk_socpeople integer NOT NULL, tms TIMESTAMP, import_key VARCHAR(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_societe_contacts ADD UNIQUE INDEX idx_societe_contacts_idx1 (entity, fk_soc, fk_c_type_contact, fk_socpeople)');
        $this->addSql('CREATE TABLE llx_categorie (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, fk_parent integer DEFAULT 0 NOT NULL, label varchar(180) NOT NULL, ref_ext varchar(255), type integer DEFAULT 1 NOT NULL, description text, color varchar(8), fk_soc integer DEFAULT NULL, extraparams varchar(255), date_creation datetime, fk_user_creat integer, fk_user_modif integer, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, visible tinyint DEFAULT 1 NOT NULL, position integer DEFAULT 0, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_categorie ADD UNIQUE INDEX uk_categorie_ref (entity, fk_parent, label, type)');
        $this->addSql('CREATE TABLE llx_categories_extrafields (rowid integer AUTO_INCREMENT PRIMARY KEY, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, fk_object integer NOT NULL, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_categories_extrafields ADD UNIQUE INDEX uk_categories_extrafields (fk_object)');
        $this->addSql('CREATE TABLE llx_categorie_societe (fk_categorie integer NOT NULL, fk_soc integer NOT NULL, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_categorie_societe ADD PRIMARY KEY pk_categorie_societe (fk_categorie, fk_soc)');
        $this->addSql('ALTER TABLE llx_categorie_societe ADD INDEX idx_categorie_societe_fk_categorie (fk_categorie)');
        $this->addSql('ALTER TABLE llx_categorie_societe ADD INDEX idx_categorie_societe_fk_societe (fk_soc)');
        $this->addSql('CREATE TABLE llx_categorie_fournisseur (fk_categorie integer NOT NULL, fk_soc integer NOT NULL, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_categorie_fournisseur ADD PRIMARY KEY pk_categorie_fournisseur (fk_categorie, fk_soc)');
        $this->addSql('ALTER TABLE llx_categorie_fournisseur ADD INDEX idx_categorie_fournisseur_fk_categorie (fk_categorie)');
        $this->addSql('ALTER TABLE llx_categorie_fournisseur ADD INDEX idx_categorie_fournisseur_fk_societe (fk_soc)');
        $this->addSql('CREATE TABLE llx_categorie_contact (fk_categorie integer NOT NULL, fk_socpeople integer NOT NULL, import_key varchar(14)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_categorie_contact ADD PRIMARY KEY pk_categorie_contact (fk_categorie, fk_socpeople)');
        $this->addSql('ALTER TABLE llx_categorie_contact ADD INDEX idx_categorie_contact_fk_categorie (fk_categorie)');
        $this->addSql('ALTER TABLE llx_categorie_contact ADD INDEX idx_categorie_contact_fk_socpeople (fk_socpeople)');
        $this->addSql('CREATE TABLE llx_c_typent (id integer PRIMARY KEY, code varchar(12) NOT NULL, libelle varchar(128), fk_country integer NULL, active tinyint DEFAULT 1 NOT NULL, module varchar(32) NULL, position integer NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_typent ADD UNIQUE INDEX uk_c_typent(code)');
        $this->addSql('CREATE TABLE llx_c_country (rowid integer PRIMARY KEY, code varchar(2) NOT NULL, code_iso varchar(3), numeric_code varchar(3), label varchar(128) NOT NULL, eec tinyint DEFAULT 0 NOT NULL, sepa tinyint DEFAULT 0 NOT NULL, active tinyint DEFAULT 1 NOT NULL, favorite tinyint DEFAULT 0 NOT NULL, phone_code integer, trunk_prefix varchar(5)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_country ADD UNIQUE INDEX idx_c_country_code (code)');
        $this->addSql('ALTER TABLE llx_c_country ADD UNIQUE INDEX idx_c_country_code_iso (code_iso)');
        $this->addSql('ALTER TABLE llx_c_country ADD UNIQUE INDEX idx_c_country_label (label)');
        $this->addSql('CREATE TABLE llx_c_departements (rowid integer AUTO_INCREMENT PRIMARY KEY, code_departement varchar(6) NOT NULL, fk_region integer, cheflieu varchar(50), tncc integer, ncc varchar(50), nom varchar(50), active tinyint DEFAULT 1 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_departements ADD UNIQUE uk_departements (code_departement,fk_region)');
        $this->addSql('ALTER TABLE llx_c_departements ADD INDEX idx_departements_fk_region (fk_region)');
        $this->addSql('CREATE TABLE llx_c_paiement (id integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, code varchar(6) NOT NULL, libelle varchar(128), type smallint, active tinyint DEFAULT 1 NOT NULL, accountancy_code varchar(32) NULL, module varchar(32) NULL, position integer NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_paiement ADD UNIQUE INDEX uk_c_paiement_code(entity, code)');
        $this->addSql('CREATE TABLE llx_c_payment_term (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, code varchar(16), sortorder smallint, active tinyint DEFAULT 1, libelle varchar(255), libelle_facture text, type_cdr tinyint, nbjour smallint, decalage smallint, deposit_percent varchar(63) DEFAULT NULL, module varchar(32) NULL, position integer NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_payment_term ADD UNIQUE INDEX uk_c_payment_term_code(entity, code)');
        $this->addSql('CREATE TABLE llx_c_incoterms (rowid integer AUTO_INCREMENT PRIMARY KEY, code varchar(8) NOT NULL, label varchar(100), libelle varchar(255) NOT NULL, active tinyint DEFAULT 1 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_incoterms ADD UNIQUE INDEX uk_c_incoterms (code)');
        $this->addSql('CREATE TABLE llx_c_stcomm (id integer PRIMARY KEY, code varchar(24) NOT NULL, libelle varchar(128), picto varchar(128), sortorder smallint DEFAULT 0, active tinyint default 1 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_stcomm ADD UNIQUE INDEX uk_c_stcomm(code)');
        $this->addSql('CREATE TABLE llx_c_stcommcontact (id integer PRIMARY KEY, code varchar(12) NOT NULL, libelle varchar(128), picto varchar(128), active tinyint default 1 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_stcommcontact ADD UNIQUE INDEX uk_c_stcommcontact(code)');
        $this->addSql('CREATE TABLE llx_c_effectif (id integer PRIMARY KEY, code varchar(12) NOT NULL, libelle varchar(128), active tinyint DEFAULT 1 NOT NULL, module varchar(32) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_effectif ADD UNIQUE INDEX uk_c_effectif(code)');
        $this->addSql('CREATE TABLE llx_c_forme_juridique (rowid integer AUTO_INCREMENT PRIMARY KEY, code integer NOT NULL, fk_pays integer NOT NULL, libelle varchar(255), isvatexempted tinyint DEFAULT 0 NOT NULL, active tinyint DEFAULT 1 NOT NULL, module varchar(32) NULL, position integer NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_forme_juridique ADD UNIQUE INDEX uk_c_forme_juridique (code)');
        $this->addSql('CREATE TABLE llx_c_civility (rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, code varchar(6) NOT NULL, label varchar(128), active tinyint DEFAULT 1 NOT NULL, module varchar(32) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_civility ADD UNIQUE INDEX uk_c_civility(code)');
        $this->addSql('CREATE TABLE llx_c_type_contact (rowid integer AUTO_INCREMENT PRIMARY KEY, element varchar(64) NOT NULL, source varchar(8) DEFAULT \'external\' NOT NULL, code varchar(32) NOT NULL, libelle varchar(128) NOT NULL, active tinyint DEFAULT 1 NOT NULL, module varchar(32) NULL, position integer NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_type_contact ADD UNIQUE INDEX uk_c_type_contact_id (element, source, code)');
        $this->addSql('ALTER TABLE llx_c_type_contact ADD INDEX idx_c_type_contact_code (code)');
        $this->addSql('CREATE TABLE llx_c_input_method (rowid integer AUTO_INCREMENT PRIMARY KEY, code varchar(30), libelle varchar(128), active tinyint default 1 NOT NULL, module varchar(32) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_c_input_method ADD UNIQUE INDEX uk_c_input_method(code)');
        $this->addSql('CREATE TABLE llx_c_prospectlevel (code varchar(12) PRIMARY KEY, label varchar(128), sortorder smallint, active smallint DEFAULT 1 NOT NULL, module varchar(32) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_societe_log (id integer AUTO_INCREMENT PRIMARY KEY, datel datetime, fk_soc integer, fk_statut integer, fk_user integer, author varchar(30), label varchar(128)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_user (rowid integer AUTO_INCREMENT PRIMARY KEY, login varchar(50) NOT NULL, firstname varchar(50), lastname varchar(50), entity integer DEFAULT 1 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE llx_user ADD UNIQUE INDEX uk_user_login (login, entity)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS llx_user');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_log');
        $this->addSql('DROP TABLE IF EXISTS llx_c_prospectlevel');
        $this->addSql('DROP TABLE IF EXISTS llx_c_input_method');
        $this->addSql('DROP TABLE IF EXISTS llx_c_type_contact');
        $this->addSql('DROP TABLE IF EXISTS llx_c_civility');
        $this->addSql('DROP TABLE IF EXISTS llx_c_forme_juridique');
        $this->addSql('DROP TABLE IF EXISTS llx_c_effectif');
        $this->addSql('DROP TABLE IF EXISTS llx_c_stcommcontact');
        $this->addSql('DROP TABLE IF EXISTS llx_c_stcomm');
        $this->addSql('DROP TABLE IF EXISTS llx_c_incoterms');
        $this->addSql('DROP TABLE IF EXISTS llx_c_payment_term');
        $this->addSql('DROP TABLE IF EXISTS llx_c_paiement');
        $this->addSql('DROP TABLE IF EXISTS llx_c_departements');
        $this->addSql('DROP TABLE IF EXISTS llx_c_country');
        $this->addSql('DROP TABLE IF EXISTS llx_c_typent');
        $this->addSql('DROP TABLE IF EXISTS llx_categorie_contact');
        $this->addSql('DROP TABLE IF EXISTS llx_categorie_fournisseur');
        $this->addSql('DROP TABLE IF EXISTS llx_categorie_societe');
        $this->addSql('DROP TABLE IF EXISTS llx_categories_extrafields');
        $this->addSql('DROP TABLE IF EXISTS llx_categorie');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_contacts');
        $this->addSql('DROP TABLE IF EXISTS llx_socpeople_extrafields');
        $this->addSql('DROP TABLE IF EXISTS llx_socpeople');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_perentity');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_account');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_rib');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_remise_except');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_remise');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_prices');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_commerciaux');
        $this->addSql('DROP TABLE IF EXISTS llx_societe_extrafields');
        $this->addSql('DROP TABLE IF EXISTS llx_societe');
    }
}
