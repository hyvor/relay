<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251027161113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create infrastructure_bounces table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TABLE infrastructure_bounces (
                id SERIAL PRIMARY KEY,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                is_read BOOLEAN NOT NULL,
                smtp_code INT NOT NULL,
                smtp_enhanced_code TEXT NOT NULL,
                smtp_message TEXT NOT NULL,
                send_recipient_id INTEGER NOT NULL,
            )
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE infrastructure_bounces");
    }
}
