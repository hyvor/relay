<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251125025738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create lock_keys table for distributed locking';
    }

    public function up(Schema $schema): void
    {
        // explicitly create the lock_keys table than dynamically doing in the Lock component
        // IF NOT EXISTS to prevent breaking older deployments
        $this->addSql(
            <<<SQL
CREATE TABLE IF NOT EXISTS lock_keys (
    key_id VARCHAR(64) NOT NULL, 
    key_token VARCHAR(44) NOT NULL, 
    key_expiration INT NOT NULL, 
    PRIMARY KEY(key_id)
);
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE lock_keys');
    }
}
