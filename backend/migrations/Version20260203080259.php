<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203080259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add schema changes for organizations migration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
			ALTER TABLE projects
				ADD COLUMN organization_id BIGINT DEFAULT NULL;
			SQL
        );

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
