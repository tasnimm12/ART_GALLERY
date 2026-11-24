<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903114600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add JSON roles column to user and migrate existing Role assignments into it';
    }

    public function up(Schema $schema): void
    {
        // add roles column (JSON). If your MySQL/MariaDB version does not support JSON, adjust to TEXT
        // create roles column immediately
        $this->connection->executeStatement("ALTER TABLE `user` ADD COLUMN `roles` JSON NOT NULL DEFAULT '[]'");

        // Migrate existing role assignments from role/user_role into user.roles
        $users = $this->connection->fetchAllAssociative('SELECT id FROM `user`');
        foreach ($users as $u) {
            $userId = $u['id'];
            $rows = $this->connection->fetchAllAssociative('SELECT r.name FROM `role` r JOIN `user_role` ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$userId]);
            $names = array_map(fn($r) => $r['name'], $rows);
            if (empty($names)) {
                $names = ['ROLE_USER'];
            }
            $this->connection->executeStatement('UPDATE `user` SET roles = ? WHERE id = ?', [json_encode($names), $userId]);
        }
    }

    public function down(Schema $schema): void
    {
        // remove roles column
        $this->addSql('ALTER TABLE `user` DROP COLUMN `roles`');
    }
}
