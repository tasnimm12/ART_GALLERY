<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006141200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional manual daily artwork selection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artwork ADD daily_featured TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_artwork_daily_featured ON artwork (daily_featured)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_artwork_daily_featured ON artwork');
        $this->addSql('ALTER TABLE artwork DROP daily_featured');
    }
}
