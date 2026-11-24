<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store locally analyzed dominant colors for each artwork';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE artwork_color_analysis (id INT AUTO_INCREMENT NOT NULL, artwork_id INT NOT NULL, color_id INT NOT NULL, hex_code VARCHAR(7) NOT NULL, percentage NUMERIC(5, 2) NOT NULL, source_hash VARCHAR(64) NOT NULL, analyzed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_artwork_color_analysis_color (artwork_id, color_id), INDEX idx_artwork_color_analysis_color (color_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE artwork_color_analysis ADD CONSTRAINT FK_ARTWORK_COLOR_ANALYSIS_ARTWORK FOREIGN KEY (artwork_id) REFERENCES artwork (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE artwork_color_analysis ADD CONSTRAINT FK_ARTWORK_COLOR_ANALYSIS_COLOR FOREIGN KEY (color_id) REFERENCES artwork_color (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE artwork_color_analysis');
    }
}
