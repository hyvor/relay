<?php

namespace App\Service\Send\MessageHandler;

use App\Service\Send\Message\ClearExpiredSendsMessage;
use App\Service\Send\SendContentStorage;
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

        do {
            /** @var string[] $uuids */
            $uuids = $this->em->getConnection()->fetchFirstColumn(
                <<<SQL
                DELETE FROM sends
                WHERE id IN (
                    SELECT id FROM sends
                    WHERE created_at <= :date
                    LIMIT :limit
                )
                RETURNING uuid
                SQL,
                ['date' => $cutoff, 'limit' => self::BATCH_SIZE],
                ['date' => Types::DATETIME_IMMUTABLE, 'limit' => Types::INTEGER]
            );

            foreach ($uuids as $uuid) {
                $this->sendContentStorage->delete($uuid);
            }
        } while (count($uuids) === self::BATCH_SIZE);
    }

}
