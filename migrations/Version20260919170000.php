<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Third-party API slice (src/ThirdParty): upstream tables referenced by the
 * ported endpoints that were missing from the base migration —
 * llx_notify_def + llx_c_action_trigger (notifications), llx_c_regions
 * (joined by Societe::fetch), llx_c_tva (getTaxesFromId for discounts),
 * llx_societe_remise_supplier (set_remise_supplier), llx_multicurrency +
 * llx_multicurrency_rate (discount multicurrency rate), and the llx_user
 * columns returned by getSalesRepresentatives.
 * DDL is copied verbatim from upstream htdocs/install/mysql/tables/.
 */
final class Version20260919170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Third-party API slice: add llx_notify_def, llx_c_action_trigger, llx_c_regions, llx_c_tva, llx_societe_remise_supplier, llx_multicurrency(_rate) and llx_user representative columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE llx_notify_def (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, datec date, fk_action integer NOT NULL, fk_soc integer, fk_contact integer, fk_user integer, email varchar(255), threshold double(24,8), context varchar(128), type varchar(16) DEFAULT \'email\') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_c_action_trigger (rowid integer AUTO_INCREMENT PRIMARY KEY, elementtype varchar(64) NOT NULL, code varchar(128) NOT NULL, contexts varchar(255) NULL, label varchar(128) NOT NULL, description varchar(255), enabled varchar(255), rang integer DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_c_regions (rowid integer AUTO_INCREMENT PRIMARY KEY, code_region integer NOT NULL, fk_pays integer NOT NULL, cheflieu varchar(50), tncc integer, nom varchar(100), active tinyint DEFAULT 1 NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_c_tva (rowid integer NOT NULL AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, fk_pays integer NOT NULL, fk_department_buyer integer DEFAULT NULL, code varchar(10) DEFAULT \'\', type_vat smallint NOT NULL DEFAULT 0, taux double NOT NULL, localtax1 varchar(20) NOT NULL DEFAULT \'0\', localtax1_type varchar(10) NOT NULL DEFAULT \'0\', localtax2 varchar(20) NOT NULL DEFAULT \'0\', localtax2_type varchar(10) NOT NULL DEFAULT \'0\', use_default tinyint DEFAULT 0, recuperableonly integer NOT NULL DEFAULT 0, einvoice_vatex varchar(32), note varchar(128), active tinyint DEFAULT 1 NOT NULL, accountancy_code_sell varchar(32) DEFAULT NULL, accountancy_code_buy varchar(32) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_societe_remise_supplier (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, fk_soc integer NOT NULL, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, datec datetime, fk_user_author integer, remise_supplier double(7,4) DEFAULT 0 NOT NULL, note text) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_multicurrency (rowid integer AUTO_INCREMENT PRIMARY KEY, date_create datetime DEFAULT NULL, code varchar(255) DEFAULT NULL, name varchar(255) DEFAULT NULL, entity integer DEFAULT 1, fk_user integer DEFAULT NULL, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE llx_multicurrency_rate (rowid integer AUTO_INCREMENT PRIMARY KEY, date_sync datetime DEFAULT NULL, rate double NOT NULL DEFAULT 0, rate_direct double DEFAULT 0, fk_multicurrency integer NOT NULL, entity integer DEFAULT 1, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // columns read by Societe::getSalesRepresentatives (mode 0) on llx_user
        $this->addSql('ALTER TABLE llx_user ADD COLUMN gender varchar(10), ADD COLUMN job varchar(128), ADD COLUMN office_phone varchar(30), ADD COLUMN office_fax varchar(30), ADD COLUMN user_mobile varchar(30), ADD COLUMN personal_mobile varchar(30), ADD COLUMN email varchar(255), ADD COLUMN photo varchar(255), ADD COLUMN statut tinyint DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE llx_notify_def');
        $this->addSql('DROP TABLE llx_c_action_trigger');
        $this->addSql('DROP TABLE llx_c_regions');
        $this->addSql('DROP TABLE llx_c_tva');
        $this->addSql('DROP TABLE llx_societe_remise_supplier');
        $this->addSql('DROP TABLE llx_multicurrency');
        $this->addSql('DROP TABLE llx_multicurrency_rate');
        $this->addSql('ALTER TABLE llx_user DROP COLUMN gender, DROP COLUMN job, DROP COLUMN office_phone, DROP COLUMN office_fax, DROP COLUMN user_mobile, DROP COLUMN personal_mobile, DROP COLUMN email, DROP COLUMN photo, DROP COLUMN statut');
    }
}
