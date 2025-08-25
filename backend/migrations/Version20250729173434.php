<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250729173434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create debug_incoming_emails table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE debug_incoming_emails_type AS ENUM ('bounce', 'complaint')");
        $this->addSql("CREATE TYPE debug_incoming_emails_status AS ENUM ('success', 'failed')");

        $this->addSql(<<<SQL
            CREATE TABLE debug_incoming_emails (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                type debug_incoming_emails_type NOT NULL,
                status debug_incoming_emails_status NOT NULL,
                raw_email TEXT NOT NULL,
                mail_from TEXT NOT NULL,
                rcpt_to TEXT NOT NULL,
                parsed_data JSONB, -- DSN or ARF data
                error_message TEXT DEFAULT NULL
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE debug_incoming_emails");
    }
}
