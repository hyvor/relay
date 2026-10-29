<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029105702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nat_check column to servers table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers ADD COLUMN nat_check JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE servers DROP COLUMN nat_check');
    }
}
