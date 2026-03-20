<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320111539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fixes dkim_selector uniqueness';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE domains ADD CONSTRAINT domains_domain_dkim_selector_key UNIQUE (domain, dkim_selector)');
        $this->addSql('ALTER TABLE domains DROP CONSTRAINT domains_dkim_selector_key');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
