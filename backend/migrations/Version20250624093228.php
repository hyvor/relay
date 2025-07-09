<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250624093228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Create webhook_deliveries table";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TYPE webhook_delivery_status AS ENUM ('pending', 'delivered', 'failed');");

        $this->addSql(
        <<<SQL
            CREATE TABLE webhook_deliveries (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                webhook_id BIGINT NOT NULL references webhooks(id) ON DELETE CASCADE,
                url VARCHAR(255) NOT NULL,
                event VARCHAR(255) NOT NULL,
                status webhook_delivery_status NOT NULL DEFAULT 'pending',
                request_body TEXT NOT NULL,
                response TEXT
            );
         SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE webhook_deliveries");
    }
}
