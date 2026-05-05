<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260505100305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ip_address_id to sends table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends ADD ip_address_id INT DEFAULT NULL REFERENCES ip_addresses(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sends DROP ip_address_id');
    }
}
