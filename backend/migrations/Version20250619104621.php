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
            CREATE TYPE scope AS ENUM ('full', 'send_email');
        SQL
        );

        $this->addSql(
        <<<SQL
            CREATE TABLE api_keys (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
                key CHAR(64) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                scope scope NOT NULL DEFAULT 'send_email',
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
