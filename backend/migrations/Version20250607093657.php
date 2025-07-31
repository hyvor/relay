<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250607093657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create projects table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE project_send_type AS ENUM ('transactional', 'distributional')");
        $this->addSql('
        CREATE TABLE projects (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            hyvor_user_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            send_type project_send_type NOT NULL
        )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE projects');
    }
}
