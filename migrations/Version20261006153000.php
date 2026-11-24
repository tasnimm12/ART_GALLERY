<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align analyzed_at column metadata with Doctrine datetime mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artwork_color_analysis CHANGE analyzed_at analyzed_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE artwork_color_analysis CHANGE analyzed_at analyzed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
