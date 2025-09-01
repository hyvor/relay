<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250825180500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create send_feedback table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE send_feedback_type AS ENUM ('bounce', 'complaint')");

        $this->addSql(<<<SQL
            CREATE TABLE send_feedback (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                type send_feedback_type NOT NULL,
                send_recipient_id INTEGER NOT NULL references send_recipients(id) ON DELETE CASCADE,
                debug_incoming_email_id INTEGER NOT NULL references debug_incoming_emails(id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql(
            "CREATE INDEX idx_send_feedback_send_recipient_id ON send_feedback (send_recipient_id)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE send_feedback");
    }
}

