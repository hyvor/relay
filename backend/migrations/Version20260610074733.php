<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610074733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bounce_reason to send_recipients and send_attempt_recipients';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE send_recipients ADD COLUMN bounce_reason VARCHAR(20) NULL");
        $this->addSql("ALTER TABLE send_attempt_recipients ADD COLUMN bounce_reason VARCHAR(20) NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE send_recipients DROP COLUMN bounce_reason");
        $this->addSql("ALTER TABLE send_attempt_recipients DROP COLUMN bounce_reason");
    }
}
