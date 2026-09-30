<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930041148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stats, bounce reasons, complaint feedback and provider metrics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE bounce_reason AS ENUM ('recipient', 'infrastructure', 'unknown')");
        $this->addSql("ALTER TABLE send_recipients ADD COLUMN bounce_reason bounce_reason NULL");
        $this->addSql("ALTER TABLE send_attempt_recipients ADD COLUMN bounce_reason bounce_reason NULL");

        $this->addSql("
            CREATE TABLE stats_project (
                project_id BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                stat_date DATE NOT NULL,
                sends INT DEFAULT 0,
                send_recipients INT DEFAULT 0,
                send_attempts INT DEFAULT 0,
                accepted INT DEFAULT 0,
                deferred INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                bounced_unknown INT DEFAULT 0,
                complained INT DEFAULT 0,
                suppressed INT DEFAULT 0,
                failed INT DEFAULT 0,
                accepted_rate NUMERIC(6,4),
                deferred_rate NUMERIC(6,4),
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                bounced_unknown_rate NUMERIC(6,4),
                complained_rate NUMERIC(7,6),
                suppressed_rate NUMERIC(6,4),
                failed_rate NUMERIC(6,4),
                PRIMARY KEY (project_id, stat_date)
            )
        ");

        $this->addSql("
            CREATE TABLE stats_ip (
                ip_address_id BIGINT NOT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                stat_date DATE NOT NULL,
                sends INT DEFAULT 0,
                send_recipients INT DEFAULT 0,
                send_attempts INT DEFAULT 0,
                accepted INT DEFAULT 0,
                deferred INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                bounced_unknown INT DEFAULT 0,
                complained INT DEFAULT 0,
                suppressed INT DEFAULT 0,
                failed INT DEFAULT 0,
                accepted_rate NUMERIC(6,4),
                deferred_rate NUMERIC(6,4),
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                bounced_unknown_rate NUMERIC(6,4),
                complained_rate NUMERIC(7,6),
                suppressed_rate NUMERIC(6,4),
                failed_rate NUMERIC(6,4),
                PRIMARY KEY (ip_address_id, stat_date)
            )
        ");

        $this->addSql("
            CREATE TABLE stats_ip_project (
                ip_address_id BIGINT NOT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                project_id BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                stat_date DATE NOT NULL,
                sent INT DEFAULT 0,
                accepted INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                bounced_unknown INT DEFAULT 0,
                complained INT DEFAULT 0,
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                bounced_unknown_rate NUMERIC(6,4),
                complained_rate NUMERIC(7,6),
                PRIMARY KEY (ip_address_id, project_id, stat_date)
            )
        ");

        $this->addSql("
            CREATE TABLE stats_delivery_domain (
                project_id BIGINT NULL REFERENCES projects(id) ON DELETE CASCADE,
                ip_address_id BIGINT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                recipient_domain TEXT NOT NULL,
                stat_date DATE NOT NULL,
                sent INT DEFAULT 0,
                accepted INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                bounced_unknown INT DEFAULT 0,
                complained INT DEFAULT 0,
                complained_rate NUMERIC(7,6)
            )
        ");

        $this->addSql("
            CREATE UNIQUE INDEX uniq_stats_delivery_domain
            ON stats_delivery_domain (project_id, ip_address_id, recipient_domain, stat_date)
            NULLS NOT DISTINCT
        ");

        $this->addSql(
            '
            ALTER TABLE send_feedback
                ADD COLUMN project_id BIGINT DEFAULT NULL REFERENCES projects(id) ON DELETE CASCADE,
                ADD COLUMN send_id BIGINT DEFAULT NULL REFERENCES sends(id) ON DELETE CASCADE,
                ADD COLUMN ip_address_id BIGINT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                ADD COLUMN detail TEXT DEFAULT NULL,
                ADD COLUMN processed_at TIMESTAMPTZ DEFAULT NULL,
                ALTER COLUMN send_recipient_id DROP NOT NULL
            ',
        );

        $this->addSql('CREATE INDEX idx_send_feedback_send_id ON send_feedback (send_id)');
        $this->addSql('CREATE INDEX idx_send_feedback_unprocessed ON send_feedback (id) WHERE processed_at IS NULL');

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
