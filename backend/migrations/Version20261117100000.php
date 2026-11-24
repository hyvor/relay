<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'introduces cloud queues';
    }

    public function up(Schema $schema): void
    {
        // drop types and constraints
        $this->addSql("ALTER TABLE queues ALTER COLUMN type DROP DEFAULT");
        $this->addSql("ALTER TABLE queues ALTER COLUMN type TYPE VARCHAR(32) USING type::text");
        $this->addSql("DROP TYPE queue_type");
        $this->addSql("ALTER TABLE queues DROP CONSTRAINT queues_name_key");

        // swap type and name
        $this->addSql("UPDATE queues SET type = name, name = 'default'");

        // create new queue_type enum and alter column type
        $this->addSql("CREATE TYPE queue_type AS ENUM ('transactional', 'distributional', 'dedicated')");
        $this->addSql("ALTER TABLE queues ALTER COLUMN type TYPE queue_type USING type::queue_type");

        // constraints and indexes
        $this->addSql("ALTER TABLE queues ADD COLUMN dedicated_organization_id BIGINT DEFAULT NULL");
        $this->addSql(
            <<<SQL
            ALTER TABLE queues ADD CONSTRAINT chk_queues_dedicated_organization_id
            CHECK ((type = 'dedicated') = (dedicated_organization_id IS NOT NULL))
            SQL
        );
        $this->addSql(
            <<<SQL
            CREATE UNIQUE INDEX uniq_queues_type_name ON queues (type, name)
            WHERE type IN ('transactional', 'distributional')
            SQL
        );
        $this->addSql(
            <<<SQL
            CREATE UNIQUE INDEX uniq_queues_dedicated_organization_id_name ON queues (dedicated_organization_id, name)
            WHERE type = 'dedicated'
            SQL
        );

        // cache queue_id in projects table
        $this->addSql(
            "ALTER TABLE projects ADD COLUMN queue_id INTEGER DEFAULT NULL REFERENCES queues (id) ON DELETE SET NULL"
        );
        $this->addSql("CREATE INDEX idx_projects_queue_id ON projects (queue_id)");
    }

    public function down(Schema $schema): void
    {
    }
}
