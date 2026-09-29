<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925090709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend send_feedback to store complaint events';
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

        $this->addSql('CREATE INDEX idx_send_feedback_send_id ON send_feedback (send_id)');
        $this->addSql('CREATE INDEX idx_send_feedback_unprocessed ON send_feedback (id) WHERE processed_at IS NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
