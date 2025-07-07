<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250707174213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create app_logs table";
    }

    public function up(Schema $schema): void
    {
        // Create app_logs table
        $this->addSql(
            <<<SQL
            CREATE TABLE app_logs (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                level TEXT NOT NULL,
                message TEXT NOT NULL,
                context JSONB
            );
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE app_logs");
    }
}
