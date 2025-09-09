<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812144231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create server_tasks tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE task_enum AS ENUM ('update_state')");

        $this->addSql(
            <<<SQL
        CREATE TABLE server_tasks
        (
          id SERIAL PRIMARY KEY,
          created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
          updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
          server_id BIGINT NOT NULL references servers(id) ON DELETE CASCADE,
          type task_enum,
          payload JSONB
        );
        SQL
        );

        $this->addSql("CREATE INDEX idx_server_tasks_server_id ON server_tasks (server_id)");
    }

    public function down(Schema $schema): void
    {
    }
}
