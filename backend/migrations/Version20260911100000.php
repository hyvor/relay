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
            CREATE TYPE kyc_content_ownership_enum AS ENUM ('self', 'third_party');
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TYPE kyc_status_enum AS ENUM ('pending', 'approved', 'rejected');
        SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE kyc (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                organization_id BIGINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                account_type kyc_account_type_enum NOT NULL,
                country VARCHAR(255) NOT NULL,
                address TEXT NOT NULL,
                website VARCHAR(255) NOT NULL,
                content_ownership kyc_content_ownership_enum NOT NULL,
                sending_type JSON NOT NULL,
                use_case TEXT NOT NULL,
                status kyc_status_enum NOT NULL DEFAULT 'pending',
                submitted_at TIMESTAMPTZ NOT NULL,
                UNIQUE (organization_id)
            );
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE kyc');
        $this->addSql('DROP TYPE kyc_status_enum');
        $this->addSql('DROP TYPE kyc_content_ownership_enum');
        $this->addSql('DROP TYPE kyc_account_type_enum');
    }
}
