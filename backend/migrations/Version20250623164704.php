<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250623164704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create webhooks table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        <<<SQL
            CREATE TABLE webhooks (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
                url VARCHAR(255) NOT NULL,
                description TEXT,
                events jsonb NOT NULL,
                secret_encrypted TEXT NOT NULL
            );
         SQL
        );

        $this->addSql("CREATE INDEX idx_webhooks_project_id ON webhooks (project_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE webhooks");
    }
}
