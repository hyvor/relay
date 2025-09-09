<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250607093658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create domains table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE domain_status AS ENUM ('pending', 'active', 'warning', 'suspended')");
        $this->addSql(
            <<<SQL
        CREATE TABLE domains (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
            domain VARCHAR(255) NOT NULL,
            status domain_status NOT NULL DEFAULT 'pending',
            status_changed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            dkim_selector VARCHAR(255) NOT NULL UNIQUE,
            dkim_public_key TEXT NOT NULL,
            dkim_private_key_encrypted TEXT NOT NULL,
            dkim_checked_at TIMESTAMPTZ,
            dkim_error_message TEXT,
                
            UNIQUE(project_id, domain)
        )
        SQL
        );

        $this->addSql("CREATE INDEX idx_domains_project_id ON domains(project_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE domains");
    }
}
