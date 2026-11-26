<?php

namespace App\Service\Queue;

use App\Entity\IpAddress;
use App\Entity\Project;
use App\Entity\Queue;
use App\Entity\Send;
use App\Entity\Type\QueueType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class QueueService
{

    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return Queue[]
     */
    public function getAllQueues(): array
    {
        return $this->em->getRepository(Queue::class)->findBy([], ['type' => 'ASC', 'name' => 'ASC']);
    }

    public function getQueueById(int $id): ?Queue
    {
        return $this->em->getRepository(Queue::class)->find($id);
    }

    public function getQueue(QueueType $type, string $name): ?Queue
    {
        assert($type !== QueueType::DEDICATED, 'Use dedicated queue methods for dedicated queues');

        return $this->em->getRepository(Queue::class)->findOneBy([
            'type' => $type,
            'name' => $name,
        ]);
    }

    /**
     * @return Queue[] ordered by id
     */
    public function getQueuesByType(QueueType $type): array
    {
        return $this->em->getRepository(Queue::class)->findBy(['type' => $type], ['id' => 'ASC']);
    }

    public function selectQueue(QueueType $type, int $projectId): Queue
    {
        assert($type !== QueueType::DEDICATED, 'Dedicated queues cannot be selected by type');

        return $this->pickQueue($this->getQueuesByType($type), $projectId)
            ?? throw new \LogicException("No queues found for type {$type->value}");
    }

    /**
     * @param Queue[] $queues
     */
    public function pickQueue(array $queues, int $projectId): ?Queue
    {
        $queues = array_values($queues);

        if (count($queues) === 0) {
            return null;
        }

        return $queues[$projectId % count($queues)];
    }

    public function createQueue(
        QueueType $type,
        string $name,
        ?int $dedicatedOrganizationId = null,
        bool $flush = true,
    ): Queue {
        assert(
            ($type === QueueType::DEDICATED) === ($dedicatedOrganizationId !== null),
            'dedicatedOrganizationId must be set if and only if the type is dedicated'
        );

        $queue = new Queue();
        $queue->setCreatedAt($this->now());
        $queue->setUpdatedAt($this->now());
        $queue->setName($name);
        $queue->setType($type);
        $queue->setDedicatedOrganizationId($dedicatedOrganizationId);

        $this->em->persist($queue);

        if ($flush) {
            $this->em->flush();
        }

        return $queue;
    }

    public function deleteQueue(Queue $queue): void
    {
        $this->em->remove($queue);
        $this->em->flush();
    }

    public function hasSends(Queue $queue): bool
    {
        $result = $this->em->getRepository(Send::class)
            ->createQueryBuilder('s')
            ->select('1')
            ->where('s.queue = :queue')
            ->setParameter('queue', $queue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    public function hasIpAddresses(Queue $queue): bool
    {
        return $this->em->getRepository(IpAddress::class)->count(['queue' => $queue]) > 0;
    }

    /**
     * @return array<int, int> queue id => number of projects routed to the queue
     */
    public function getProjectCountsByQueue(): array
    {
        /** @var array<array{queue_id: int, count: int}> $rows */
        $rows = $this->em->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->select('IDENTITY(p.queue) AS queue_id, COUNT(p.id) AS count')
            ->where('p.queue IS NOT NULL')
            ->groupBy('p.queue')
            ->orderBy('queue_id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['queue_id']] = (int)$row['count'];
        }

        return $counts;
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
