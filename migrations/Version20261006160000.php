<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve order and auction history when artworks are removed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item ADD artwork_title VARCHAR(255) DEFAULT NULL, ADD artist_name VARCHAR(150) DEFAULT NULL');
        $this->addSql('UPDATE order_item oi INNER JOIN artwork a ON a.id = oi.artwork_id INNER JOIN artist ar ON ar.id = a.artist_id SET oi.artwork_title = a.title, oi.artist_name = ar.name');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09DB8FFA4');
        $this->addSql('ALTER TABLE order_item CHANGE artwork_id artwork_id INT DEFAULT NULL, CHANGE artwork_title artwork_title VARCHAR(255) NOT NULL, CHANGE artist_name artist_name VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09DB8FFA4 FOREIGN KEY (artwork_id) REFERENCES artwork (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE auction ADD artwork_title VARCHAR(255) DEFAULT NULL, ADD artist_name VARCHAR(150) DEFAULT NULL');
        $this->addSql('UPDATE auction auc INNER JOIN artwork a ON a.id = auc.artwork_id INNER JOIN artist ar ON ar.id = a.artist_id SET auc.artwork_title = a.title, auc.artist_name = ar.name');
        $this->addSql('ALTER TABLE auction DROP FOREIGN KEY FK_DEE4F593DB8FFA4');
        $this->addSql('ALTER TABLE auction CHANGE artwork_id artwork_id INT DEFAULT NULL, CHANGE artwork_title artwork_title VARCHAR(255) NOT NULL, CHANGE artist_name artist_name VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE auction ADD CONSTRAINT FK_DEE4F593DB8FFA4 FOREIGN KEY (artwork_id) REFERENCES artwork (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE direct_sale DROP FOREIGN KEY FK_B48760CCDB8FFA4');
        $this->addSql('ALTER TABLE direct_sale ADD CONSTRAINT FK_B48760CCDB8FFA4 FOREIGN KEY (artwork_id) REFERENCES artwork (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM order_item WHERE artwork_id IS NULL') > 0
            || (int) $this->connection->fetchOne('SELECT COUNT(*) FROM auction WHERE artwork_id IS NULL') > 0,
            'This migration cannot be reverted after artworks have been deleted because their original relations cannot be reconstructed.',
        );

        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09DB8FFA4');
        $this->addSql('ALTER TABLE order_item CHANGE artwork_id artwork_id INT NOT NULL, DROP artwork_title, DROP artist_name');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09DB8FFA4 FOREIGN KEY (artwork_id) REFERENCES artwork (id)');

        $this->addSql('ALTER TABLE auction DROP FOREIGN KEY FK_DEE4F593DB8FFA4');
        $this->addSql('ALTER TABLE auction CHANGE artwork_id artwork_id INT NOT NULL, DROP artwork_title, DROP artist_name');
        $this->addSql('ALTER TABLE auction ADD CONSTRAINT FK_DEE4F593DB8FFA4 FOREIGN KEY (artwork_id) REFERENCES artwork (id)');

        $this->addSql('ALTER TABLE direct_sale DROP FOREIGN KEY FK_B48760CCDB8FFA4');
        $this->addSql('ALTER TABLE direct_sale ADD CONSTRAINT FK_B48760CCDB8FFA4 FOREIGN KEY (artwork_id) REFERENCES artwork (id)');
    }
}
