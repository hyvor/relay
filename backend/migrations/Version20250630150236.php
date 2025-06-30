<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250630150236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create api_idempotency_records table";
    }

    public function up(Schema $schema): void
    {
        // Create api_idempotency_records table
        $this->addSql(
            <<<SQL
   CREATE TABLE api_idempotency_records (
       id SERIAL PRIMARY KEY,
       created_at TIMESTAMPTZ NOT NULL,
       updated_at TIMESTAMPTZ NOT NULL,
       project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
       idempotency_key TEXT NOT NULL,
       endpoint TEXT NOT NULL,
       response JSONB,
       status_code INTEGER NOT NULL,
       UNIQUE (project_id, idempotency_key)
   );
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE api_idempotency_records");
    }
}
