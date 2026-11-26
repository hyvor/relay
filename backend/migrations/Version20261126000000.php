<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'send_attempt_recipients: status of the attempt only (recipient_status -> status)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TYPE send_attempt_recipients_status AS ENUM ('accepted', 'deferred', 'bounced', 'failed')
        ");

        // an attempt only stores what the SMTP server returned at that moment.
        // later complaints were written over the last attempt, but the server had accepted the message
        $this->addSql("
            ALTER TABLE send_attempt_recipients
                ALTER COLUMN recipient_status TYPE send_attempt_recipients_status
                USING (CASE WHEN recipient_status::TEXT = 'complained' THEN 'accepted' ELSE recipient_status::TEXT END)::send_attempt_recipients_status
        ");
        $this->addSql("ALTER TABLE send_attempt_recipients RENAME COLUMN recipient_status TO status");
    }

    public function down(Schema $schema): void {}
}
