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
        $this->addSql("
            CREATE TABLE stats_project (
                project_id BIGINT NOT NULL REFERENCES projects(id),
                stat_date DATE NOT NULL,
                sends INT DEFAULT 0,
                send_recipients INT DEFAULT 0,
                send_attempts INT DEFAULT 0,
                accepted INT DEFAULT 0,
                deferred INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                complained INT DEFAULT 0,
                suppressed INT DEFAULT 0,
                failed INT DEFAULT 0,
                accepted_rate NUMERIC(6,4),
                deferred_rate NUMERIC(6,4),
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                complained_rate NUMERIC(6,4),
                suppressed_rate NUMERIC(6,4),
                failed_rate NUMERIC(6,4),
                PRIMARY KEY (project_id, stat_date)
            )
        ");

        $this->addSql("
            CREATE TABLE stats_ip (
                ip_address INET NOT NULL,
                stat_date DATE NOT NULL,
                sends INT DEFAULT 0,
                send_recipients INT DEFAULT 0,
                send_attempts INT DEFAULT 0,
                accepted INT DEFAULT 0,
                deferred INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                complained INT DEFAULT 0,
                suppressed INT DEFAULT 0,
                failed INT DEFAULT 0,
                accepted_rate NUMERIC(6,4),
                deferred_rate NUMERIC(6,4),
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                complained_rate NUMERIC(6,4),
                suppressed_rate NUMERIC(6,4),
                failed_rate NUMERIC(6,4),
                PRIMARY KEY (ip_address, stat_date)
            )
        ");

        $this->addSql("
            CREATE TABLE stats_ip_project (
                ip_address INET NOT NULL,
                project_id BIGINT NOT NULL REFERENCES projects(id),
                stat_date DATE NOT NULL,
                sent INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                complained INT DEFAULT 0,
                bounced_recipient_rate NUMERIC(6,4),
                bounced_infrastructure_rate NUMERIC(6,4),
                complained_rate NUMERIC(6,4),
                PRIMARY KEY (ip_address, project_id, stat_date)
            )
        ");

        $this->addSql("
            CREATE TABLE stats_delivery_domain (
                project_id BIGINT NOT NULL REFERENCES projects(id),
                ip_address INET NOT NULL,
                recipient_domain TEXT NOT NULL,
                provider TEXT NULL,
                stat_date DATE NOT NULL,
                sent INT DEFAULT 0,
                accepted INT DEFAULT 0,
                bounced_recipient INT DEFAULT 0,
                bounced_infrastructure INT DEFAULT 0,
                complained INT DEFAULT 0,
                PRIMARY KEY (project_id, ip_address, recipient_domain, stat_date)
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stats_delivery_domain');
        $this->addSql('DROP TABLE stats_ip_project');
        $this->addSql('DROP TABLE stats_ip');
        $this->addSql('DROP TABLE stats_project');
    }
}
