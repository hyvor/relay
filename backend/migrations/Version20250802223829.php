<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250802223829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create OIDC tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
        CREATE TABLE oidc_users
        (
          id          SERIAL PRIMARY KEY,
          created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
          updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
          iss         text NOT NULL,
          sub         text NOT NULL,
          email       text NOT NULL,
          name        text NOT NULL,
          picture_url text,
          UNIQUE (iss, sub)
        );
        SQL
        );

        $this->addSql(
            <<<SQL
        CREATE TABLE oidc_sessions
        (
            sess_id       VARCHAR(128) NOT NULL PRIMARY KEY,
            sess_data     BYTEA        NOT NULL,
            sess_lifetime INTEGER      NOT NULL,
            sess_time     INTEGER      NOT NULL
        );
        SQL
        );
        $this->addSql("CREATE INDEX idx_oidc_sessions_sess_lifetime ON oidc_sessions (sess_lifetime);");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE oidc_users');
        $this->addSql('DROP TABLE oidc_sessions');
        $this->addSql('DROP INDEX idx_oidc_sessions_sess_lifetime');
    }
}
