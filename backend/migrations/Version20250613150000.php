<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250613150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create send_attempts table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE send_attempt_status AS ENUM ('accepted', 'deferred', 'bounced', 'failed')");

        $this->addSql(
            <<<SQL
            CREATE TABLE send_attempts (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                send_id BIGINT NOT NULL references sends(id) ON DELETE CASCADE,
                ip_address_id BIGINT NOT NULL references ip_addresses(id) ON DELETE CASCADE,
                status send_attempt_status NOT NULL,
                try_count INT NOT NULL DEFAULT 0,
                domain text NOT NULL,
                resolved_mx_hosts jsonb NOT NULL,
                responded_mx_host text,
                smtp_conversations jsonb NOT NULL,
                recipient_ids jsonb NOT NULL,
                error text
            )
            SQL
        );

        $this->addSql("CREATE INDEX idx_send_attempts_send_id ON send_attempts (send_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE send_attempts");
    }
}
