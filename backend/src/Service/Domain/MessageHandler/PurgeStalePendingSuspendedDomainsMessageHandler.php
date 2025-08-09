<?php

namespace App\Service\Domain\MessageHandler;

use App\Service\Domain\Message\PurgeStalePendingSuspendedDomainsMessage;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Deletes stale pending domains that has its last status change
 * older than 14 days.
 */
#[AsMessageHandler]
class PurgeStalePendingSuspendedDomainsMessageHandler
{

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    private const string WHERE = "WHERE (status = 'pending' OR status = 'suspended') AND status_changed_at < :date";

    public function __invoke(PurgeStalePendingSuspendedDomainsMessage $message): void
    {
        $cutoffDate = new \DateTimeImmutable()->modify('-14 days')->format('Y-m-d H:i:s');
        $where = self::WHERE;

        // get count
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM domains $where",
            ['date' => $cutoffDate]
        );
        $count = is_scalar($count) ? (int)$count : 0;

        if ($count === 0) {
            $this->logger->info(
                'No stale pending or suspended domains to purge',
                [
                    'handler' => self::class
                ]
            );
            return;
        }

        $this->logger->info(
            'Purging stale pending and suspended domains',
            [
                'handler' => self::class,
                'count' => $count,
                'cutoff_date' => $cutoffDate
            ]
        );

        // delete
        $this->connection->executeStatement(
            "DELETE FROM domains $where",
            ['date' => $cutoffDate]
        );
    }

}