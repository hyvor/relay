<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251119041929 extends AbstractMigration
{

    public function getDescription(): string
    {
        return 'Create tls_certificates table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TYPE tls_certificate_status AS ENUM ('pending', 'active', 'failed', 'expired', 'revoked')"
        );

        $this->addSql(
            <<<SQL
        CREATE TABLE tls_certificates (
            id serial PRIMARY KEY,
            created_at timestamptz NOT NULL,
            updated_at timestamptz NOT NULL,
            type TEXT NOT NULL, -- "mail" only for now
            domain TEXT NOT NULL,
            status tls_certificate_status NOT NULL,
            private_key_encrypted TEXT NOT NULL, -- PEM format, encrypted
            certificate TEXT, -- PEM format
            valid_from timestamptz,
            valid_to timestamptz,
        )
        SQL
        );

        $this->addSql(
            "ALTER TABLE instances ADD COLUMN mail_tls_certificate_id bigint DEFAULT NULL references tls_certificates(id) ON DELETE SET NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tls_certificates');
    }
}
