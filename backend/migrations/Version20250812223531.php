<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250812223531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create project_users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
        CREATE TABLE project_users
        (
            id          SERIAL PRIMARY KEY,
            created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            user_id     BIGINT      NOT NULL,
            project_id  BIGINT      NOT NULL REFERENCES projects (id) ON DELETE CASCADE,
            scopes      JSONB       NOT NULL DEFAULT '[]',
            UNIQUE (user_id, project_id)
        );
        SQL
        );

        $this->addSql("CREATE INDEX idx_project_users_project_id ON project_users (project_id)");
        $this->addSql("CREATE INDEX idx_project_users_user_id ON project_users (user_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE project_users');
    }
}
