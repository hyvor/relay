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
        $this->addSql("CREATE TYPE sends_status AS ENUM ('queued', 'sent', 'failed')");

        $this->addSql(<<<SQL
        CREATE TABLE sends (
            id SERIAL PRIMARY KEY,
            uuid UUID NOT NULL UNIQUE,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            sent_at TIMESTAMPTZ,
            failed_at TIMESTAMPTZ,
            status sends_status NOT NULL,
            project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
            domain_id BIGINT references domains(id),
            queue_id BIGINT references queues(id),
            email VARCHAR(255),
            content_html TEXT,
            content_text TEXT,
            from_address VARCHAR(255),
            to_address text,
            subject text,
            body_html text,
            body_text text
        )
        SQL);

        $this->addSql("CREATE INDEX idx_sends_project_id ON sends (project_id)");
        $this->addSql("CREATE INDEX idx_sends_domain_id ON sends (domain_id)");
        $this->addSql("CREATE INDEX idx_sends_queue_id ON sends (queue_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE sends");
    }
}
