<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250731232854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the cache_items table';
    }

    public function up(Schema $schema): void
    {

        // https://supun.io/symfony-database-cache
        $this->addSql(<<<SQL
        CREATE TABLE cache_items (
            item_id varchar(255) NOT NULL PRIMARY KEY,
            item_data bytea NOT NULL,
            item_lifetime int4,
            item_time int4 NOT NULL
        );
        SQL);

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cache_items');
    }
}
