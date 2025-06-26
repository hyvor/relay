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
        $this->addSql('
        CREATE TABLE domains (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
            domain VARCHAR(255) NOT NULL,
            dkim_selector VARCHAR(255) NOT NULL UNIQUE,
            dkim_public_key TEXT NOT NULL,
            dkim_private_key_encrypted TEXT NOT NULL,
            
            dkim_verified BOOLEAN NOT NULL DEFAULT FALSE,
            dkim_checked_at TIMESTAMPTZ,
            dkim_error_message TEXT,
            
            UNIQUE(project_id, domain)
        )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE domains");
    }
}
