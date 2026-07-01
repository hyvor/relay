<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505100305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ip_address_id to sends table and add warmup_schedules table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends ADD ip_address_id INT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX idx_sends_ip_address_id ON sends (ip_address_id)');

        $this->addSql(
            "
            CREATE TABLE warmup_schedules (
                id SERIAL PRIMARY KEY,
                ip_address_id INTEGER NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                warmup_status VARCHAR(10) NOT NULL DEFAULT 'warming',
                warmup_started_date DATE NULL,
                warmup_sent_today INTEGER NOT NULL DEFAULT 0,
                warmup_max_today INTEGER NOT NULL DEFAULT 0,
                warmup_schedule JSON NULL
            )
            "
        );
        $this->addSql("ALTER TABLE warmup_schedules ADD CONSTRAINT FK_warmup_schedules_ip_address FOREIGN KEY (ip_address_id) REFERENCES ip_addresses (id)");
        $this->addSql("CREATE INDEX idx_warmup_schedules_warmup_status ON warmup_schedules (warmup_status)");
        $this->addSql("CREATE INDEX idx_warmup_schedules_ip_address_id ON warmup_schedules (ip_address_id)");

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sends_ip_address_id');
        $this->addSql('ALTER TABLE sends DROP ip_address_id');

        $this->addSql("DROP TABLE warmup_schedules");
    }
}
