<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006131600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add artwork color palette and artwork color assignments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE artwork_color (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, slug VARCHAR(50) NOT NULL, hex VARCHAR(7) NOT NULL, UNIQUE INDEX uniq_artwork_color_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('INSERT INTO artwork_color (name, slug, hex) VALUES
            (\'Noir\', \'noir\', \'#171717\'),
            (\'Blanc\', \'blanc\', \'#F5F1E9\'),
            (\'Rouge\', \'rouge\', \'#B83A34\'),
            (\'Bleu\', \'bleu\', \'#426C9A\'),
            (\'Vert\', \'vert\', \'#4F7657\'),
            (\'Jaune\', \'jaune\', \'#D7B642\'),
            (\'Orange\', \'orange\', \'#C97632\'),
            (\'Violet\', \'violet\', \'#79579B\'),
            (\'Multicolore\', \'multicolore\', \'#B88945\')');
        $this->addSql('CREATE TABLE artwork_color_link (artwork_id INT NOT NULL, color_id INT NOT NULL, INDEX IDX_53961C577ADA1FB5 (color_id), PRIMARY KEY(artwork_id, color_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE artwork_color_link ADD CONSTRAINT FK_97D441DFE5A0D2D1 FOREIGN KEY (artwork_id) REFERENCES artwork (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE artwork_color_link ADD CONSTRAINT FK_97D441DF7B7C144 FOREIGN KEY (color_id) REFERENCES artwork_color (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE artwork_color_link');
        $this->addSql('DROP TABLE artwork_color');
    }
}
