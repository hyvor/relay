<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private_ip_address column to ip_addresses and nat_check column to servers for NAT support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_addresses ADD COLUMN private_ip_address INET DEFAULT NULL');
        $this->addSql(
            'CREATE UNIQUE INDEX idx_ip_addresses_server_id_private_ip_address ON ip_addresses (server_id, private_ip_address)',
        );
        $this->addSql('ALTER TABLE servers ADD COLUMN nat_check JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_addresses DROP COLUMN private_ip_address');
        $this->addSql('ALTER TABLE servers DROP COLUMN nat_check');
    }
}
