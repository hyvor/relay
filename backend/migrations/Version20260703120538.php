<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;

final class Version20260703120538 extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        private Filesystem $filesystem,
    ) {
        parent::__construct($connection, $logger);
    }

    public function getDescription(): string
    {
        return 'Migrate email content columns to storage and drop them from sends';
    }

    public function up(Schema $schema): void
    {
        $this->migrateDataToStorage();

        $this->addSql('ALTER TABLE sends DROP COLUMN body_html');
        $this->addSql('ALTER TABLE sends DROP COLUMN body_text');
        $this->addSql('ALTER TABLE sends DROP COLUMN headers');
        $this->addSql('ALTER TABLE sends DROP COLUMN raw');
    }

    private function migrateDataToStorage(): void
    {
        $lastId = 0;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, uuid, body_html, body_text, headers, raw
                 FROM sends
                 WHERE id > :lastId
                 ORDER BY id ASC
                 LIMIT :limit',
                ['lastId' => $lastId, 'limit' => 100],
                ['lastId' => ParameterType::INTEGER, 'limit' => ParameterType::INTEGER]
            );

            if (count($rows) === 0) {
                break;
            }

            foreach ($rows as $row) {
                $uuid = (string)$row['uuid'];
                $rawPath = 'sends/' . $uuid . '.eml';

                if (!$this->filesystem->fileExists($rawPath)) {
                    $this->filesystem->write($rawPath, (string)($row['raw'] ?? ''));
                }

                $lastId = (int)$row['id'];
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}

