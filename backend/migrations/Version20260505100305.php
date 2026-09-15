<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505100305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ip_address_id to sends table and add warmup_schedules table with refactored columns, enum, results, updated_at, inline FK, and unique index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends ADD ip_address_id INT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_sends_ip_address_id ON sends (ip_address_id)');

        $this->addSql("CREATE TYPE warmup_status_enum AS ENUM('warming', 'warmed', 'cancelled')");

        $this->addSql(
            "
            CREATE TABLE warmup_schedules (
                id SERIAL PRIMARY KEY,
                ip_address_id INTEGER NOT NULL REFERENCES ip_addresses (id) ON DELETE CASCADE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status warmup_status_enum NOT NULL DEFAULT 'warming',
                started_date DATE NOT NULL,
                sent_today INTEGER NOT NULL,
                max_today INTEGER NOT NULL DEFAULT 0,
                schedule JSON NOT NULL,
                results JSON NOT NULL DEFAULT '[]'
            )
            "
        );

        $this->addSql("CREATE INDEX idx_warmup_schedules_status ON warmup_schedules (status)");
        $this->addSql("CREATE INDEX idx_warmup_schedules_ip_address_id ON warmup_schedules (ip_address_id)");

        $this->addSql("CREATE UNIQUE INDEX uniq_warmup_schedules_ip_warming ON warmup_schedules (ip_address_id) WHERE status = 'warming'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sends_ip_address_id');
        $this->addSql('ALTER TABLE sends DROP ip_address_id');

        $this->addSql("DROP TABLE warmup_schedules");

        // Drop enum type
        $this->addSql('DROP TYPE IF EXISTS warmup_status_enum');
    }
}
