<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250613093659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create sends table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TABLE sends (
                id SERIAL PRIMARY KEY,
                uuid UUID NOT NULL UNIQUE DEFAULT gen_random_uuid(),
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                queued BOOLEAN NOT NULL DEFAULT true,
                send_after TIMESTAMPTZ NOT NULL,
                project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
                domain_id BIGINT NOT NULL references domains(id) ON DELETE CASCADE,
                queue_id BIGINT NOT NULL references queues(id),
                queue_name text NOT NULL, -- denormalized
                from_address text NOT NULL,
                from_name text,
                subject text,
                body_html text,
                body_text text,
                headers jsonb,
                message_id text NOT NULL UNIQUE,
                raw text NOT NULL,
                size_bytes INT NOT NULL
            )
            SQL
        );

        $this->addSql("CREATE INDEX idx_sends_project_id ON sends (project_id)");
        $this->addSql("CREATE INDEX idx_sends_domain_id ON sends (domain_id)");
        $this->addSql("CREATE INDEX idx_sends_queue_id ON sends (queue_id)");

        // worker index
        $this->addSql(
            "CREATE INDEX idx_sends_worker ON sends (queue_id, send_after) WHERE queued = true"
        );

        // send_recipients table
        $this->addSql("CREATE TYPE send_recipients_type AS ENUM ('to', 'cc', 'bcc')");
        $this->addSql(
            "CREATE TYPE send_recipients_status AS ENUM ('queued', 'accepted', 'deferred', 'bounced', 'failed', 'complained', 'suppressed')"
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE send_recipients (
                id SERIAL PRIMARY KEY,
                send_id BIGINT NOT NULL references sends(id) ON DELETE CASCADE,
                type send_recipients_type NOT NULL,
                address text NOT NULL,
                name text NOT NULL, -- empty if not provided
                status send_recipients_status NOT NULL DEFAULT 'queued',
                try_count INT NOT NULL DEFAULT 0,
                UNIQUE (send_id, address, type)
            )
            SQL
        );
        $this->addSql("CREATE INDEX idx_send_recipients_send_id ON send_recipients (send_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE sends");
    }
}
