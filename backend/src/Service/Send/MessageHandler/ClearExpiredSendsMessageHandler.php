<?php

namespace App\Service\Send\MessageHandler;

use App\Service\Send\Exception\SendContentStorageException;
use App\Service\Send\Message\ClearExpiredSendsMessage;
use App\Service\Send\SendContentStorage;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearExpiredSendsMessageHandler
{

    public const int RETENTION_DAYS = 30;
    private const int BATCH_SIZE = 1000;

    public function __construct(
        private EntityManagerInterface $em,
        private SendContentStorage $sendContentStorage,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ClearExpiredSendsMessage $message): void
    {
        $cutoff = new \DateTimeImmutable('-' . self::RETENTION_DAYS . ' days');

        $connection = $this->em->getConnection();
        $lastId = 0;

        do {
            /** @var array<array{id: int, uuid: string}> $sends */
            $sends = $connection->fetchAllAssociative(
                <<<SQL
                SELECT id, uuid FROM sends
                WHERE created_at <= :date AND id > :lastId
                ORDER BY id
                LIMIT :limit
                SQL,
                ['date' => $cutoff, 'lastId' => $lastId, 'limit' => self::BATCH_SIZE],
                ['date' => Types::DATETIME_IMMUTABLE, 'lastId' => Types::INTEGER, 'limit' => Types::INTEGER]
            );

            if (count($sends) === 0) {
                break;
            }

            $lastId = $sends[count($sends) - 1]['id'];

            $deletedIds = [];
            foreach ($sends as $send) {
                try {
                    $this->sendContentStorage->delete($send['uuid']);
                    $deletedIds[] = $send['id'];
                } catch (SendContentStorageException $e) {
                    $this->logger->error('Failed to delete send content', [
                        'send_id' => $send['id'],
                        'uuid' => $send['uuid'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($deletedIds) {
                $connection->executeStatement(
                    'DELETE FROM sends WHERE id IN (:ids)',
                    ['ids' => $deletedIds],
                    ['ids' => ArrayParameterType::INTEGER]
                );
            }
        } while (count($sends) === self::BATCH_SIZE);

        $this->em->createQuery(<<<DQL
            DELETE FROM App\Entity\ProviderMetric pm
            WHERE pm.metric_date <= :date
        DQL)
            ->setParameter('date', $cutoff)
            ->execute();
    }

}
