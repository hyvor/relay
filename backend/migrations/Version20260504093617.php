<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504093617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allowed_ips column to api_keys table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE api_keys ADD COLUMN allowed_ips TEXT[] NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void {}
}
