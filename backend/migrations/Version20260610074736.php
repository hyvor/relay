<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610074736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stats rollup tables';
    }

    public function up(Schema $schema): void
    {

        // project stats are aggregated by send_recipient status
        $this->addSql("
            CREATE TABLE stats_project (
                project_id BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                stat_date DATE NOT NULL,
                sends INT DEFAULT 0,
                send_recipients INT DEFAULT 0,
                queued INT DEFAULT 0,
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

        // the rest of the stats tables are aggregated by send_attempt_recipient

        $this->addSql("
            CREATE TABLE stats_ip (
                ip_address_id BIGINT NOT NULL REFERENCES ip_addresses(id) ON DELETE CASCADE,
                stat_date DATE NOT NULL,
                sends INT DEFAULT 0,
                send_recipients INT DEFAULT 0,
                send_attempts INT DEFAULT 0,
                attempted INT DEFAULT 0,
                accepted INT DEFAULT 0,
                deferred INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                bounced_unknown INT DEFAULT 0,
                failed INT DEFAULT 0,
                accepted_rate NUMERIC(6,4),
                deferred_rate NUMERIC(6,4),
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                bounced_unknown_rate NUMERIC(6,4),
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
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                bounced_unknown_rate NUMERIC(6,4),
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
                bounced_unknown INT DEFAULT 0
            )
        ");

        $this->addSql("
            CREATE UNIQUE INDEX uniq_stats_delivery_domain
            ON stats_delivery_domain (project_id, ip_address_id, recipient_domain, stat_date)
            NULLS NOT DISTINCT
        ");


        // dates (of stats) that need to be re-counted by the next stats update
        $this->addSql("
            CREATE TABLE stats_outdated_dates (
                stat_date DATE NOT NULL PRIMARY KEY
            )
        ");

        // finding the last attempt of a recipient (async bounces, complaints, stats)
        $this->addSql('CREATE INDEX idx_send_attempt_recipients_send_recipient_id ON send_attempt_recipients (send_recipient_id)');

        // for DELETE of stats for rebuilding
        $this->addSql("CREATE INDEX idx_stats_delivery_domain_stat_date ON stats_delivery_domain (stat_date)");
        $this->addSql("CREATE INDEX idx_stats_project_stat_date ON stats_project (stat_date)");
        $this->addSql("CREATE INDEX idx_stats_ip_stat_date ON stats_ip (stat_date)");
        $this->addSql("CREATE INDEX idx_stats_ip_project_stat_date ON stats_ip_project (stat_date)");
    }

    public function down(Schema $schema): void {}
}
