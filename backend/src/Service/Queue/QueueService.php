<?php

namespace App\Service\Queue;

use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Entity\Type\QueueType;
use Doctrine\ORM\EntityManagerInterface;

class QueueService
{

    public const string TRANSACTIONAL_QUEUE_NAME = 'transactional';
    public const string DISTRIBUTIONAL_QUEUE_NAME = 'distributional';

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return Queue[]
     */
    public function getAllQueues(): array
    {
        return $this->em->getRepository(Queue::class)->findAll();
    }

    private function getQueueByName(string $name): ?Queue
    {
        return $this->em->getRepository(Queue::class)->findOneBy(['name' => $name]);
    }

    public function getQueueById(int $id): ?Queue
    {
        return $this->em->getRepository(Queue::class)->find($id);
    }

    public function getTransactionalQueue(): ?Queue
    {
        return $this->getQueueByName(self::TRANSACTIONAL_QUEUE_NAME);
    }

    public function getDistributionalQueue(): ?Queue
    {
        return $this->getQueueByName(self::DISTRIBUTIONAL_QUEUE_NAME);
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

    private function createQueue(
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

    /**
     * @param int[] $ignoredIpAddressIds
     * @return Queue[]
     */
    public function getQueuesThatHaveNoIpAddresses(array $ignoredIpAddressIds = []): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('q')
            ->from(Queue::class, 'q')
            ->orderBy('q.id', 'ASC');

        $subQuery = 'SELECT i.id FROM ' . IpAddress::class . ' i WHERE i.queue = q';
        if (count($ignoredIpAddressIds) > 0) {
            $subQuery .= ' AND i.id NOT IN (:ignoredIpAddressIds)';
            $qb->setParameter('ignoredIpAddressIds', $ignoredIpAddressIds);
        }

        /** @var Queue[] $queues */
        $queues = $qb->where("NOT EXISTS ($subQuery)")->getQuery()->getResult();

        return $queues;
    }

    public function getQueuesCount(): int
    {
        return (int)$this->em->getRepository(Queue::class)
            ->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
