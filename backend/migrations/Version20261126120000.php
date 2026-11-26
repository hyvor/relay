<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add uuid to send_recipients (sent as X-Relay-Recipient header)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE send_recipients ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid()');
        $this->addSql('CREATE UNIQUE INDEX send_recipients_uuid_unique ON send_recipients (uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX send_recipients_uuid_unique');
        $this->addSql('ALTER TABLE send_recipients DROP COLUMN uuid');
    }
}
