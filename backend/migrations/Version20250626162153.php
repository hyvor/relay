<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250626162153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create suppressions table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TYPE reason_enum AS ENUM ('bounce', 'complaint');
        SQL
        );

        // Create suppressions table
        $this->addSql(
        <<<SQL
            CREATE TABLE suppressions (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
                email VARCHAR(255) NOT NULL,
                reason reason_enum NOT NULL,
                description TEXT,
                UNIQUE (project_id, email)
            );
         SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE suppressions");
    }
}
