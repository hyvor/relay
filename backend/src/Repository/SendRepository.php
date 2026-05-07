<?php

namespace App\Repository;

use App\Entity\Send;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Send>
 */
class SendRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Send::class);
    }

    public function updateNullIpSendsForQueue(int $queueId, ?int $ipAddressId): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'UPDATE sends SET ip_address_id = :ip_id, updated_at = NOW() WHERE queue_id = :queue_id AND ip_address_id IS NULL';
        $stmt = $conn->prepare($sql);
        return $stmt->executeStatement([
            'ip_id' => $ipAddressId,
            'queue_id' => $queueId,
        ]);
    }
}