<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505100305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ip_address_id to sends table and add warmup columns to ip_addresses table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends ADD ip_address_id INT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE SET NULL');
		$this->addSql('CREATE INDEX idx_sends_ip_address_id ON sends (ip_address_id)');

        $this->addSql(
            "
            ALTER TABLE ip_addresses
            ADD COLUMN warmup_status VARCHAR(10) NOT NULL DEFAULT 'warming',
            ADD COLUMN warmup_started_date DATE NULL,
            ADD COLUMN warmup_sent_today INTEGER NOT NULL DEFAULT 0,
            ADD COLUMN warmup_max_today INTEGER NOT NULL DEFAULT 0,
            ADD COLUMN warmup_schedule JSON NULL
            "
        );
        $this->addSql("CREATE INDEX idx_ip_addresses_warmup_status ON ip_addresses (warmup_status)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sends_ip_address_id');
		$this->addSql('ALTER TABLE sends DROP ip_address_id');

        $this->addSql("DROP INDEX IF EXISTS idx_ip_addresses_warmup_status");
        $this->addSql(
            "
            ALTER TABLE ip_addresses
            DROP COLUMN warmup_status,
            DROP COLUMN warmup_started_date,
            DROP COLUMN warmup_sent_today,
            DROP COLUMN warmup_max_today,
            DROP COLUMN warmup_schedule
            "
        );
    }
}
