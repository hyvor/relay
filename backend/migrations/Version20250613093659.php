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
            "CREATE TYPE sends_status AS ENUM ('queued', 'processing', 'accepted', 'bounced', 'complained')"
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE sends (
                id SERIAL PRIMARY KEY,
                uuid UUID NOT NULL UNIQUE DEFAULT gen_random_uuid(),
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                send_after TIMESTAMPTZ NOT NULL,
                sent_at TIMESTAMPTZ,
                failed_at TIMESTAMPTZ,
                status sends_status NOT NULL,
                project_id BIGINT NOT NULL references projects(id) ON DELETE CASCADE,
                domain_id BIGINT NOT NULL references domains(id) ON DELETE CASCADE,
                queue_id BIGINT NOT NULL references queues(id),
                queue_name text NOT NULL, -- denormalized
                from_address text NOT NULL,
                from_name text,
                to_address text NOT NULL,
                to_name text,
                subject text,
                body_html text,
                body_text text,
                headers jsonb,
                message_id text NOT NULL,
                raw text NOT NULL,
                result jsonb,
                try_count INT NOT NULL DEFAULT 0
            )
            SQL
        );

        $this->addSql("CREATE INDEX idx_sends_project_id ON sends (project_id)");
        $this->addSql("CREATE INDEX idx_sends_domain_id ON sends (domain_id)");
        $this->addSql("CREATE INDEX idx_sends_queue_id ON sends (queue_id)");
        $this->addSql("CREATE INDEX idx_sends_created_at ON sends (created_at)");

        // worker index
        $this->addSql("CREATE INDEX idx_sends_status_queue_id_send_after ON sends (queue_id, send_after) WHERE status = 'queued'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE sends");
    }
}
