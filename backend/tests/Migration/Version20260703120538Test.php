<?php

namespace App\Tests\Migration;

use App\Tests\Case\KernelTestCase;
use Doctrine\DBAL\Connection;
use DoctrineMigrations\Version20260703120538;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Uid\Uuid;

// s3 migration

#[CoversNothing]
class Version20260703120538Test extends KernelTestCase
{
    private const PREVIOUS_VERSION = 'DoctrineMigrations\\Version20260504093617';
    private const TARGET_VERSION = 'DoctrineMigrations\\Version20260703120538';

    public function testDataMigrationToFilesystem(): void
    {
        $tester = $this->commandTester('doctrine:migrations:migrate');
        /** @var Connection $connection */
        $connection = $this->em->getConnection();
        /** @var Filesystem $filesystem */
        $filesystem = $this->container->get(Filesystem::class);

        $connection->executeStatement('DROP SCHEMA public CASCADE; CREATE SCHEMA public;');

        $code = $tester->execute([
            'version' => self::PREVIOUS_VERSION,
            '--no-interaction' => true,
        ]);

        if ($code !== 0) {
            throw new \RuntimeException("Migrate failed: " . $tester->getDisplay());
        }

        $projectId = (int) $connection->fetchOne(
            "INSERT INTO projects (created_at, updated_at, user_id, name, send_type, organization_id)
             VALUES (NOW(), NOW(), 1, 'Migration Test Project', 'transactional', 1)
             RETURNING id"
        );

        $domainId = (int) $connection->fetchOne(
            "INSERT INTO domains (created_at, updated_at, project_id, domain, status, status_changed_at, dkim_selector, dkim_public_key, dkim_private_key_encrypted)
             VALUES (NOW(), NOW(), ?, 'example.com', 'pending', NOW(), 'selector', 'public-key', 'private-key-encrypted')
             RETURNING id",
            [$projectId]
        );

        $queueId = (int) $connection->fetchOne(
            "INSERT INTO queues (created_at, updated_at, name, type)
             VALUES (NOW(), NOW(), 'default', 'default')
             RETURNING id"
        );

        $sends = [
            [
                'uuid' => Uuid::v4()->toString(),
                'body_html' => '<h1>HTML Content 1</h1>',
                'body_text' => 'Plain text content 1',
                'headers' => ['X-Header-1' => 'Value 1'],
                'raw' => 'Raw email content 1',
            ],
            [
                'uuid' => Uuid::v4()->toString(),
                'body_html' => '<h2>HTML Content 2</h2>',
                'body_text' => null,
                'headers' => ['X-Header-2' => 'Value 2'],
                'raw' => 'Raw email content 2',
            ],
            [
                'uuid' => Uuid::v4()->toString(),
                'body_html' => null,
                'body_text' => 'Plain text content 3',
                'headers' => [],
                'raw' => 'Raw email content 3',
            ],
        ];

        foreach ($sends as $send) {
            $connection->executeStatement(
                "INSERT INTO sends (uuid, created_at, updated_at, queued, send_after, project_id, domain_id, queue_id, queue_name, from_address, subject, body_html, body_text, headers, raw, message_id, size_bytes)
                 VALUES (?, NOW(), NOW(), true, NOW(), ?, ?, ?, 'default', 'test@example.com', 'Subject', ?, ?, ?::jsonb, ?, ?, 100)",
                [
                    $send['uuid'],
                    $projectId,
                    $domainId,
                    $queueId,
                    $send['body_html'],
                    $send['body_text'],
                    json_encode($send['headers']),
                    $send['raw'],
                    $send['uuid'] . '@msg',
                ]
            );
        }

        $code = $tester->execute([
            'version' => self::TARGET_VERSION,
            '--no-interaction' => true,
        ]);

        if ($code !== 0) {
            throw new \RuntimeException("Migrate failed: " . $tester->getDisplay());
        }

        foreach ($sends as $send) {
            $uuid = $send['uuid'];

            $this->assertTrue($filesystem->fileExists('sends/' . $uuid . '.eml'));
            $this->assertSame($send['raw'], $filesystem->read('sends/' . $uuid . '.eml'));
        }
    }
}
