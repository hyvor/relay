<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250619104621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create api_keys table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        <<<SQL
            CREATE TABLE api_keys (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
                key_hashed CHAR(64) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                scopes json NOT NULL,
                is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                last_accessed_at TIMESTAMPTZ DEFAULT NULL
            );
         SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE ip_addresses");
    }
}
