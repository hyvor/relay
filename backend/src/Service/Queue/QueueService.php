<?php

namespace App\Service\Queue;

use App\Entity\Queue;
use App\Entity\Type\QueueType;
use Doctrine\ORM\EntityManagerInterface;

class QueueService
{

    public const string TRANSACTIONAL_QUEUE_NAME = 'transactional';
    public const string DISTRIBUTIONAL_QUEUE_NAME = 'distributional';

    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    /**
     * @return Queue[]
     */
    public function getAllQueues(): array
    {
        return $this->em->getRepository(Queue::class)->findAll();
    }

    public function hasDefaultQueues(): bool
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(q.id)')
            ->from(Queue::class, 'q')
            ->where('q.type = :type')
            ->setParameter('type', QueueType::DEFAULT)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function createQueue(
        string $name,
        QueueType $type
    ): Queue {
        $queue = new Queue();
        $queue->setName($name);
        $queue->setType($type);

        $this->em->persist($queue);
        $this->em->flush();

        return $queue;
    }

    public function createDefaultQueues(): void
    {
        $this->createQueue(
            self::TRANSACTIONAL_QUEUE_NAME,
            QueueType::DEFAULT
        );

        $this->createQueue(
            self::DISTRIBUTIONAL_QUEUE_NAME,
            QueueType::DEFAULT
        );
    }

}
