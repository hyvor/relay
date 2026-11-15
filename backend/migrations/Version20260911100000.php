<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create kyc table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TYPE kyc_account_type_enum AS ENUM ('individual', 'business');
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TYPE kyc_status_enum AS ENUM ('pending', 'approved', 'rejected', 'stale');
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE kyc (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                organization_id BIGINT NOT NULL,
                account_type kyc_account_type_enum NOT NULL,
                name TEXT NOT NULL,
                country TEXT NOT NULL,
                address TEXT NOT NULL,
                website TEXT NOT NULL,
                email TEXT NOT NULL,
                content_ownership JSON NOT NULL,
                sending_transactional BOOLEAN NOT NULL DEFAULT FALSE,
                sending_distributional BOOLEAN NOT NULL DEFAULT FALSE,
                use_case TEXT NOT NULL,
                status kyc_status_enum NOT NULL DEFAULT 'pending',
                note TEXT DEFAULT NULL,
                reject_reason TEXT DEFAULT NULL
            );
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE INDEX kyc_organization_id_idx ON kyc (organization_id);
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE UNIQUE INDEX kyc_pending_per_organization_id
                ON kyc (organization_id)
                WHERE status = 'pending';
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE UNIQUE INDEX kyc_active_per_organization_id
                ON kyc (organization_id)
                WHERE status IN ('approved', 'rejected');
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kyc');
        $this->addSql('DROP TYPE kyc_status_enum');
        $this->addSql('DROP TYPE kyc_account_type_enum');
    }
}
