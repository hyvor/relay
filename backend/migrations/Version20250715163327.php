<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715163327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create sudo_users table";
    }

    public function up(Schema $schema): void
    {
        // Create sudo_users table
        $this->addSql(
            <<<SQL
            CREATE TABLE sudo_users (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                hyvor_user_id BIGINT UNIQUE NOT NULL
            );
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE sudo_users");
    }
}
