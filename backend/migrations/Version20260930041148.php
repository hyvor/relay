<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930041148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add send_feedback detail, stats rebuild checkpoint to instances, and index send attempts for stats';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE send_feedback ADD COLUMN detail TEXT DEFAULT NULL');

        $this->addSql(
            '
            ALTER TABLE instances
                ADD COLUMN stats_rebuilt_at TIMESTAMPTZ DEFAULT NULL
            '
        );

        $this->addSql(
            "
            UPDATE send_feedback
            SET detail = 'recipient'
            WHERE type = 'bounce'
            "
        );

        $this->addSql('CREATE INDEX idx_send_attempts_created_at ON send_attempts (created_at)');
        $this->addSql('CREATE INDEX idx_send_attempt_recipients_send_attempt_id ON send_attempt_recipients (send_attempt_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
