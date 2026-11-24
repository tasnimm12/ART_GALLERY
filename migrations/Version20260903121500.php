<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy role and user_role tables; down() recreates and repopulates them from user.role';
    }

    public function up(Schema $schema): void
    {
        // Drop legacy user_role and role tables if they exist
        $this->addSql('DROP TABLE IF EXISTS `user_role`');
        $this->addSql('DROP TABLE IF EXISTS `role`');
    }

    public function down(Schema $schema): void
    {
        // Recreate role table
        $this->addSql("CREATE TABLE `role` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `name` VARCHAR(50) NOT NULL,
            `created_at` DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_ROLE_NAME (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // Recreate user_role join table
        $this->addSql("CREATE TABLE `user_role` (
            `user_id` INT NOT NULL,
            `role_id` INT NOT NULL,
            INDEX IDX_USER_ROLE_USER (user_id),
            INDEX IDX_USER_ROLE_ROLE (role_id),
            PRIMARY KEY(user_id, role_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // Populate role table from current user.role values
        $this->addSql("INSERT INTO role (name, created_at)
            SELECT DISTINCT role, NOW() FROM `user` WHERE role IS NOT NULL AND role != ''");

        // Populate user_role mapping
        $this->addSql("INSERT INTO user_role (user_id, role_id)
            SELECT u.id, r.id FROM `user` u JOIN role r ON r.name = u.role");

        // Add foreign keys
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_USER_ROLE_USER FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_USER_ROLE_ROLE FOREIGN KEY (role_id) REFERENCES role (id)');
    }
}
