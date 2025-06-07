<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250607093658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create domains table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
        CREATE TABLE domains (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            hyvor_user_id INTEGER NOT NULL,
            domain VARCHAR(255) NOT NULL,
            dkim_public_key TEXT NOT NULL,
            dkim_private_key TEXT NOT NULL
        )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE domains");
    }
}
