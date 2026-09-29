<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create provider_metrics table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE provider_metric_source AS ENUM ('google')");

        $this->addSql(
            '
            CREATE TABLE provider_metrics (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                source provider_metric_source NOT NULL,
                project_id BIGINT DEFAULT NULL REFERENCES projects(id) ON DELETE CASCADE,
                ip_address_id BIGINT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                metric_date DATE NOT NULL,
                value NUMERIC NOT NULL,
                processed_at TIMESTAMPTZ DEFAULT NULL
            )
            ',
        );

        $this->addSql('CREATE INDEX idx_provider_metrics_metric_date ON provider_metrics (metric_date)');
        $this->addSql('CREATE INDEX idx_provider_metrics_unprocessed ON provider_metrics (id) WHERE processed_at IS NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
