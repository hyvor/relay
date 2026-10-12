<?php

namespace App\Service\Send\MessageHandler;

use App\Service\Send\Message\ClearExpiredSendsMessage;
use App\Service\Send\SendContentStorage;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearExpiredSendsMessageHandler
{

    public const int RETENTION_DAYS = 30;
    private const int BATCH_SIZE = 1000;

    public function __construct(
        private EntityManagerInterface $em,
        private SendContentStorage $sendContentStorage,
    ) {
    }

    public function __invoke(ClearExpiredSendsMessage $message): void
    {
        $cutoff = new \DateTimeImmutable('-' . self::RETENTION_DAYS . ' days');

        $connection = $this->em->getConnection();

        do {
            /** @var array<array{id: int, uuid: string}> $sends */
            $sends = $connection->fetchAllAssociative(
                <<<SQL
                SELECT id, uuid FROM sends
                WHERE created_at <= :date
                LIMIT :limit
                SQL,
                ['date' => $cutoff, 'limit' => self::BATCH_SIZE],
                ['date' => Types::DATETIME_IMMUTABLE, 'limit' => Types::INTEGER]
            );

            if (count($sends) === 0) {
                break;
            }

            foreach ($sends as $send) {
                $this->sendContentStorage->delete($send['uuid']);
            }

            $connection->executeStatement(
                'DELETE FROM sends WHERE id IN (:ids)',
                ['ids' => array_column($sends, 'id')],
                ['ids' => ArrayParameterType::INTEGER]
            );
        } while (count($sends) === self::BATCH_SIZE);
    }

}
