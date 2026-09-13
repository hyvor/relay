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
            CREATE TYPE kyc_business_type_enum AS ENUM ('individual', 'company');
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
                full_name VARCHAR(255) NOT NULL,
                business_type kyc_business_type_enum NOT NULL,
                business_name VARCHAR(255),
                country VARCHAR(2) NOT NULL,
                address TEXT NOT NULL,
                phone VARCHAR(50) NOT NULL,
                website VARCHAR(255),
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
        $this->addSql('DROP TYPE kyc_business_type_enum');
    }
}
