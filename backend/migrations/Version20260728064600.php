<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728064600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_source column to projects table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            '
            ALTER TABLE projects
                ADD COLUMN created_by_source TEXT DEFAULT NULL,
                ALTER COLUMN user_id DROP NOT NULL
        ',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects DROP COLUMN created_by_source');
    }
}
