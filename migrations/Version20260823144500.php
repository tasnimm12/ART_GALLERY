<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823144500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing photo columns required by the admin CRUD forms.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artist ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE artwork ADD COLUMN IF NOT EXISTS photo VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artwork DROP COLUMN IF EXISTS photo');
        $this->addSql('ALTER TABLE artist DROP COLUMN IF EXISTS photo');
    }
}
