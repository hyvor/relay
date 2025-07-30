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
            domain text NOT NULL,
            dkim_public_key text NOT NULL,
            dkim_private_key_encrypted text NOT NULL,
            last_health_check_at timestamptz DEFAULT NULL,
            health_check_results jsonb DEFAULT NULL,
            private_network_cidr text DEFAULT NULL
        )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE instances');
    }
}
