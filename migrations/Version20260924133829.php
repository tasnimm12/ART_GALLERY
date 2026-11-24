<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924133829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional delivery coordinates to orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD latitude NUMERIC(10, 7) DEFAULT NULL, ADD longitude NUMERIC(10, 7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP latitude, DROP longitude');
    }
}
