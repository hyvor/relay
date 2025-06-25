<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250613112606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create ip_addresses table";
    }

    public function up(Schema $schema): void
    {
        // Create ip_addresses table
        $this->addSql('
        CREATE TABLE ip_addresses (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            server_id INTEGER NOT NULL REFERENCES servers(id) ON DELETE CASCADE,
            ip_address VARCHAR(45) NOT NULL,
            queue_id INTEGER NULL REFERENCES queues(id) ON DELETE SET NULL,
            is_available BOOLEAN NOT NULL DEFAULT FALSE, -- Indicates if the IP is (still) available in the server
            is_enabled BOOLEAN NOT NULL DEFAULT TRUE, -- Whether this IP is enabled for use to send emails
            
            is_ptr_forward_valid BOOLEAN NOT NULL DEFAULT FALSE,
            is_ptr_reverse_valid BOOLEAN NOT NULL DEFAULT FALSE,
            
            UNIQUE (server_id, ip_address)
        )
        ');

        // Create indexes
        $this->addSql("CREATE INDEX idx_ip_addresses_server_id ON ip_addresses (server_id)");
        $this->addSql("CREATE INDEX idx_ip_addresses_ip_address ON ip_addresses (ip_address)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE ip_addresses");
    }
}
