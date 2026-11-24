<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903115800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role varchar column and migrate from roles JSON array, then drop roles column';
    }

    public function up(Schema $schema): void
    {
        // 1) add role column as varchar
        $this->addSql("ALTER TABLE `user` ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'ROLE_USER'");

        // 2) populate role from roles JSON array (first element)
        $this->addSql("UPDATE `user` SET `role` = JSON_UNQUOTE(JSON_EXTRACT(roles, '$[0]')) WHERE JSON_TYPE(roles) = 'ARRAY'");

        // 3) for any rows where role is null or empty, set default
        $this->addSql("UPDATE `user` SET `role` = 'ROLE_USER' WHERE `role` IS NULL OR `role` = ''");

        // 4) drop the old roles column
        $this->addSql('ALTER TABLE `user` DROP COLUMN `roles`');
    }

    public function down(Schema $schema): void
    {
        // revert: add roles JSON column and populate from role
        $this->addSql("ALTER TABLE `user` ADD COLUMN `roles` JSON NOT NULL DEFAULT '[]'");
        $this->addSql("UPDATE `user` SET roles = JSON_ARRAY(role)");
        $this->addSql("ALTER TABLE `user` DROP COLUMN `role`");
    }
}
