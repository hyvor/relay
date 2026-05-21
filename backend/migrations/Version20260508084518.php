<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508084518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to projects table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE projects ADD COLUMN deleted_at TIMESTAMPTZ");
    }

    public function down(Schema $schema): void {
        $this->addSql("ALTER TABLE projects DROP COLUMN deleted_at");
    }
}
