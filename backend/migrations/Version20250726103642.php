<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250726103642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dns_records table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
        CREATE TABLE dns_records (
            id BIGSERIAL PRIMARY KEY,
            created_at timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
            type VARCHAR(10) NOT NULL,
            subdomain TEXT NOT NULL,
            content TEXT NOT NULL,
            ttl INT DEFAULT 300 NOT NULL,
            priority INT DEFAULT 0 NOT NULL
        );
        SQL);

        $this->addSql(
            "CREATE INDEX idx_dns_records_subdomain_type ON dns_records (subdomain, type)"
        );
    }

    public function down(Schema $schema): void
    {
        //
    }
}
