<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bounce_reason to send_recipients (set only when bounced)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE send_recipients ADD COLUMN bounce_reason bounce_reason DEFAULT NULL');

        $this->addSql(
            "
            UPDATE send_recipients sr
            SET bounce_reason = (
                SELECT sar.bounce_reason
                FROM send_attempt_recipients sar
                WHERE sar.send_recipient_id = sr.id
                ORDER BY sar.id DESC
                LIMIT 1
            )
            WHERE sr.status = 'bounced'
            "
        );
    }

    public function down(Schema $schema): void
    {
    }
}
