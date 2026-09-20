<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add llx_user.fk_socpeople — soft reference to the contact row a user is
 * linked to, read by contact/user joins (upstream llx_user.fk_socpeople).
 */
final class Version20260919210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add llx_user.fk_socpeople soft reference to llx_socpeople';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE llx_user ADD COLUMN IF NOT EXISTS fk_socpeople integer');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE llx_user DROP COLUMN IF EXISTS fk_socpeople');
    }
}
