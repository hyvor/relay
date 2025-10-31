<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251016101939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create send_attempt_recipients table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TABLE send_attempt_recipients (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                send_attempt_id BIGINT NOT NULL references send_attempts(id) ON DELETE CASCADE,
                send_recipient_id BIGINT NOT NULL references send_recipients(id) ON DELETE CASCADE,
                smtp_code INT NOT NULL,
                smtp_enhanced_code TEXT,
                smtp_message TEXT NOT NULL,
                recipient_status send_recipients_status NOT NULL,
                is_suppressed BOOLEAN DEFAULT FALSE
            )
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE send_attempt_recipients");
    }
}
