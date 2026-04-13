<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260413092452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sudo_users role';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sudo_users ADD COLUMN role TEXT NOT NULL DEFAULT 'sudo'");
    }

    public function down(Schema $schema): void {}
}
