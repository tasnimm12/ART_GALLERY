<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert auction.status to ENUM and normalize legacy CLOSED values to ENDED';
    }

    public function up(Schema $schema): void
    {
        // Normalize legacy values
        $this->addSql("UPDATE auction SET status = 'ENDED' WHERE status = 'CLOSED'");

        // Convert column to ENUM with exact allowed values
        $this->addSql("ALTER TABLE auction MODIFY status ENUM('SCHEDULED','ACTIVE','ENDED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED'");
    }

    public function down(Schema $schema): void
    {
        // Revert column back to VARCHAR(20) with default
        $this->addSql("ALTER TABLE auction MODIFY status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED'");

        // Optional: map ENDED back to CLOSED if desired (not doing that to avoid data loss)
    }
}
