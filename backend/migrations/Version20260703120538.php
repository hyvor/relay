<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703120538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop email content columns body_html, body_text, headers, raw from sends';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends DROP COLUMN body_html');
        $this->addSql('ALTER TABLE sends DROP COLUMN body_text');
        $this->addSql('ALTER TABLE sends DROP COLUMN headers');
        $this->addSql('ALTER TABLE sends DROP COLUMN raw');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends ADD COLUMN body_html TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE sends ADD COLUMN body_text TEXT DEFAULT NULL');
        $this->addSql("ALTER TABLE sends ADD COLUMN headers JSON NOT NULL DEFAULT '{}'");
        $this->addSql("ALTER TABLE sends ADD COLUMN raw TEXT NOT NULL DEFAULT ''");
    }
}
