<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private_ip_address column to ip_addresses for NAT support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_addresses ADD COLUMN private_ip_address TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ip_addresses DROP COLUMN private_ip_address');
    }
}
