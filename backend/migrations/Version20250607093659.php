<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250607093659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create sends table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
        CREATE TABLE sends (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            project_id INTEGER NOT NULL references projects(id) ON DELETE CASCADE,
            email VARCHAR(255),
            content_html TEXT,
            content_text TEXT,
            "from" VARCHAR(255)
        )
        ');

        $this->addSql(
            "CREATE INDEX idx_sends_project_id ON sends (project_id)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE sends");
    }
}
