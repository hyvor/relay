<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250612052227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create queues table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
        CREATE TABLE queues (
            id SERIAL PRIMARY KEY,
            created_at TIMESTAMPTZ NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL,
            name VARCHAR(255) NOT NULL UNIQUE
        )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE queues");
    }
}
