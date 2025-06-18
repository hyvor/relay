<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250418134054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create instances table';
    }

    public function up(Schema $schema): void
    {
        // to save instances
        // only one row will be addeed to this
        $this->addSql(<<<SQL
        CREATE TABLE instances (
            id serial PRIMARY KEY,
            created_at timestamptz NOT NULL,
            updated_at timestamptz NOT NULL,
            domain text NOT NULL
        )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE instances');
    }
}
