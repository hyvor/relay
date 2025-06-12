<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250609112605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create servers table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
        CREATE TABLE servers (
            id SERIAL PRIMARY KEY,
            created_at timestamptz NOT NULL,
            updated_at timestamptz NOT NULL,
            hostname text NOT NULL UNIQUE,
            last_ping_at timestamptz,
            api_workers integer DEFAULT 0,
            email_workers integer NOT NULL DEFAULT 0,
            webhook_workers integer NOT NULL DEFAULT 0
        )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE servers");
    }
}