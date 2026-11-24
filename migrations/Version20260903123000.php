<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter user.role column to ENUM with allowed roles';
    }

    public function up(Schema $schema): void
    {
        // Alter the role column to be an ENUM with allowed values
        $this->addSql("ALTER TABLE `user` MODIFY COLUMN `role` ENUM('ROLE_USER','ROLE_CLIENT','ROLE_ADMIN') NOT NULL DEFAULT 'ROLE_USER'");
    }

    public function down(Schema $schema): void
    {
        // Revert to a plain varchar
        $this->addSql("ALTER TABLE `user` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'ROLE_USER'");
    }
}
