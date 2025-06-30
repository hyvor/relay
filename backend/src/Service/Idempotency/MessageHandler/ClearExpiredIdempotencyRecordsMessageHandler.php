<?php

namespace App\Service\Idempotency\MessageHandler;

use App\Entity\ApiIdempotencyRecord;
use App\Service\Idempotency\Message\ClearExpiredIdempotencyRecordsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearExpiredIdempotencyRecordsMessageHandler
{

    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function __invoke(ClearExpiredIdempotencyRecordsMessage $message): void
    {

        $this->em->createQueryBuilder()
            ->delete(ApiIdempotencyRecord::class, 'r')
            ->where('r.created_at < :date')
            ->setParameter('date', new \DateTimeImmutable('-24 hours'))
            ->getQuery()
            ->execute();

        $this->em->flush();

    }

}