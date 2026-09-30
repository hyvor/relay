<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930041148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend send_feedback and index send attempts for stats';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            '
            ALTER TABLE send_feedback
                ADD COLUMN project_id BIGINT DEFAULT NULL REFERENCES projects(id) ON DELETE CASCADE,
                ADD COLUMN send_id BIGINT DEFAULT NULL REFERENCES sends(id) ON DELETE CASCADE,
                ADD COLUMN ip_address_id BIGINT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                ADD COLUMN detail TEXT DEFAULT NULL,
                ADD COLUMN processed_at TIMESTAMPTZ DEFAULT NULL,
                ALTER COLUMN send_recipient_id DROP NOT NULL
            ',
        );

        $this->addSql(
            '
            UPDATE send_feedback sf
            SET send_id = sr.send_id, project_id = s.project_id
            FROM send_recipients sr
            JOIN sends s ON s.id = sr.send_id
            WHERE sr.id = sf.send_recipient_id
            ',
        );

        $this->addSql("UPDATE send_feedback SET detail = 'recipient' WHERE type = 'bounce'");

        $this->addSql('CREATE INDEX idx_send_feedback_send_id ON send_feedback (send_id)');
        $this->addSql('CREATE INDEX idx_send_feedback_unprocessed ON send_feedback (id) WHERE processed_at IS NULL');

        $this->addSql('CREATE INDEX idx_send_attempts_created_at ON send_attempts (created_at)');
        $this->addSql('CREATE INDEX idx_send_attempt_recipients_send_attempt_id ON send_attempt_recipients (send_attempt_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
