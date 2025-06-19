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
        // Create api_keys table
        $this->addSql('
        CREATE TABLE api_keys (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
            api_key CHAR(32) NOT NULL UNIQUE,
        )
        ');

    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE ip_addresses");
    }
}
