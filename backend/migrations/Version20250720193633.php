<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720193633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create OIDC tables';
    }

    public function up(Schema $schema): void
    {
        // see hyvor/internal
        $this->addSql(<<<SQL
        CREATE TABLE oidc_users
        (
            id          SERIAL PRIMARY KEY,
            created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            sub         VARCHAR(255) NOT NULL UNIQUE,
            email       VARCHAR(255) NOT NULL UNIQUE,
            name        VARCHAR(255) NOT NULL,
            picture_url VARCHAR(255)
        );
        SQL);

        $this->addSql(<<<SQL
        CREATE TABLE oidc_sessions
        (
            sess_id       VARCHAR(128) NOT NULL PRIMARY KEY,
            sess_data     BYTEA        NOT NULL,
            sess_lifetime INTEGER      NOT NULL,
            sess_time     INTEGER      NOT NULL
        );
        SQL);
        $this->addSql("CREATE INDEX idx_oidc_sessions_sess_lifetime ON oidc_sessions (sess_lifetime);");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
