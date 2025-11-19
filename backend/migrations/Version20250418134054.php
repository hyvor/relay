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
        // only one row will be added to this
        $this->addSql(
            <<<SQL
        CREATE TABLE instances (
            id serial PRIMARY KEY,
            created_at timestamptz NOT NULL,
            updated_at timestamptz NOT NULL,
            uuid uuid NOT NULL,
            dkim_public_key text NOT NULL,
            dkim_private_key_encrypted text NOT NULL,
            system_project_id bigint NOT NULL references projects(id) ON DELETE CASCADE,
            last_health_check_at timestamptz DEFAULT NULL,
            health_check_results jsonb DEFAULT NULL,
            sudo_initialized boolean DEFAULT false,
            
            tls_certificate TEXT DEFAULT NULL,
            tls_certificate_domain TEXT DEFAULT NULL,
            tls_certificate_expires_at timestamptz DEFAULT NULL,
            tls_private_key_encrypted TEXT DEFAULT NULL,
        )
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE instances');
    }
}
