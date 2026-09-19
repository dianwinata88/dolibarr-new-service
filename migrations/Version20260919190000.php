<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the llx_user columns read by Societe::getSalesRepresentatives()
 * (office_phone, office_fax, user_mobile, personal_mobile, job, email,
 * statut, photo, gender). ADD COLUMN IF NOT EXISTS keeps this merge-safe
 * with other slices that may extend the same stub.
 */
final class Version20260919190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend llx_user stub with the sales-representative display columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE llx_user
            ADD COLUMN IF NOT EXISTS gender varchar(10),
            ADD COLUMN IF NOT EXISTS job varchar(128),
            ADD COLUMN IF NOT EXISTS office_phone varchar(30),
            ADD COLUMN IF NOT EXISTS office_fax varchar(30),
            ADD COLUMN IF NOT EXISTS user_mobile varchar(30),
            ADD COLUMN IF NOT EXISTS personal_mobile varchar(30),
            ADD COLUMN IF NOT EXISTS email varchar(255),
            ADD COLUMN IF NOT EXISTS photo varchar(255),
            ADD COLUMN IF NOT EXISTS statut tinyint DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE llx_user
            DROP COLUMN IF EXISTS gender,
            DROP COLUMN IF EXISTS job,
            DROP COLUMN IF EXISTS office_phone,
            DROP COLUMN IF EXISTS office_fax,
            DROP COLUMN IF EXISTS user_mobile,
            DROP COLUMN IF EXISTS personal_mobile,
            DROP COLUMN IF EXISTS email,
            DROP COLUMN IF EXISTS photo,
            DROP COLUMN IF EXISTS statut');
    }
}
