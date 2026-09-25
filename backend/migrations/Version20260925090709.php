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
                ADD COLUMN project_id INTEGER DEFAULT NULL REFERENCES projects(id) ON DELETE CASCADE,
                ADD COLUMN send_id INTEGER DEFAULT NULL REFERENCES sends(id) ON DELETE CASCADE,
                ADD COLUMN ip_address_id INTEGER DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE SET NULL,
                ADD COLUMN detail TEXT DEFAULT NULL,
                ALTER COLUMN send_recipient_id DROP NOT NULL
            ',
        );

        $this->addSql('CREATE INDEX idx_send_feedback_send_id ON send_feedback (send_id)');
        $this->addSql('CREATE INDEX idx_send_feedback_project_id_created_at ON send_feedback (project_id, created_at)');
        $this->addSql('CREATE INDEX idx_send_feedback_ip_address_id_created_at ON send_feedback (ip_address_id, created_at)');
    }

    public function down(Schema $schema): void
    {
    }
}
