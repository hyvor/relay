<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610074733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bounce_reason, stats rebuild checkpoint and stats indexes';
    }

    public function up(Schema $schema): void
    {
        // saving bounce reason along with recipients, attempt recipients, and feedback for stats
        $this->addSql("CREATE TYPE bounce_reason AS ENUM ('recipient', 'infrastructure', 'unknown')");
        $this->addSql("ALTER TABLE send_attempt_recipients ADD COLUMN bounce_reason bounce_reason NOT NULL DEFAULT 'unknown'");
        $this->addSql('ALTER TABLE send_recipients ADD COLUMN bounce_reason bounce_reason DEFAULT NULL');
        $this->addSql('ALTER TABLE send_feedback ADD COLUMN bounce_reason bounce_reason DEFAULT NULL');

        $this->addSql('ALTER TABLE send_feedback ADD COLUMN detail TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE instances ADD COLUMN stats_rebuilt_at TIMESTAMPTZ DEFAULT NULL');

        // uuid sent as the X-Relay-Recipient header
        $this->addSql('ALTER TABLE send_recipients ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid()');
        $this->addSql('CREATE UNIQUE INDEX send_recipients_uuid_unique ON send_recipients (uuid)');

        // indices for stats
        $this->addSql('CREATE INDEX idx_send_attempts_created_at ON send_attempts (created_at)');
        $this->addSql('CREATE INDEX idx_send_attempt_recipients_send_attempt_id ON send_attempt_recipients (send_attempt_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_send_attempt_recipients_send_attempt_id');
        $this->addSql('DROP INDEX idx_send_attempts_created_at');
        $this->addSql('ALTER TABLE instances DROP COLUMN stats_rebuilt_at');
        $this->addSql('ALTER TABLE send_feedback DROP COLUMN detail');
        $this->addSql('ALTER TABLE send_feedback DROP COLUMN bounce_reason');
        $this->addSql('ALTER TABLE send_recipients DROP COLUMN bounce_reason');
        $this->addSql("ALTER TABLE send_attempt_recipients DROP COLUMN bounce_reason");
        $this->addSql("DROP TYPE bounce_reason");
    }
}
