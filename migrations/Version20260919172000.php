<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tables required by the contacts API slice (contact.class.php touches them
 * on fetch/create/delete):
 *  - llx_element_contact   (delete cascade, load_ref_elements)
 *  - llx_user_alert        (update_perso birthday alert)
 *  - llx_mailing_unsubscribe (setNoEmail / getNoEmail)
 *  - llx_notify_def        (delete cascade — may also be created by the
 *                           thirdparties slice migration; IF NOT EXISTS)
 *  - llx_user.fk_socpeople  (contact fetch LEFT JOIN on llx_user)
 */
final class Version20260919172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact slice support tables (element_contact, user_alert, mailing_unsubscribe, notify_def) + llx_user.fk_socpeople';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS llx_element_contact (rowid integer AUTO_INCREMENT PRIMARY KEY, datecreate datetime NULL, statut smallint DEFAULT 5, element_id int NOT NULL, mandatory_signature tinyint, fk_c_type_contact int NOT NULL, fk_socpeople integer NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uk_element_contact ON llx_element_contact (element_id, fk_c_type_contact, fk_socpeople)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_element_contact_fk_socpeople ON llx_element_contact (fk_socpeople)');

        $this->addSql('CREATE TABLE IF NOT EXISTS llx_user_alert (rowid integer AUTO_INCREMENT PRIMARY KEY, type integer, fk_contact integer, fk_user integer) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->addSql("CREATE TABLE IF NOT EXISTS llx_mailing_unsubscribe (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1 NOT NULL, email varchar(255), unsubscribegroup varchar(128) DEFAULT '', ip varchar(128), date_creat datetime, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uk_mailing_unsubscribe ON llx_mailing_unsubscribe (email, entity, unsubscribegroup)');

        // already created by the thirdparties slice migration on its branch
        $this->addSql("CREATE TABLE IF NOT EXISTS llx_notify_def (rowid integer AUTO_INCREMENT PRIMARY KEY, entity integer DEFAULT 1, tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, datec date, fk_action integer NOT NULL, fk_soc integer, fk_contact integer, fk_user integer, email varchar(255), threshold double(24,8), context varchar(128), type varchar(16) DEFAULT 'email') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->addSql('ALTER TABLE llx_user ADD COLUMN IF NOT EXISTS fk_socpeople integer');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS llx_element_contact');
        $this->addSql('DROP TABLE IF EXISTS llx_user_alert');
        $this->addSql('DROP TABLE IF EXISTS llx_mailing_unsubscribe');
        $this->addSql('ALTER TABLE llx_user DROP COLUMN IF EXISTS fk_socpeople');
    }
}
