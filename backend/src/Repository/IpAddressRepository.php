<?php

namespace App\Repository;

use App\Entity\IpAddress;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\Queue;

/**
 * @extends ServiceEntityRepository<IpAddress>
 */
class IpAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IpAddress::class);
    }

    public function getRandomIpForQueue(Queue $queue): ?IpAddress
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT id FROM ip_addresses WHERE queue_id = :queue_id ORDER BY RANDOM() LIMIT 1';
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['queue_id' => $queue->getId()]);
        $id = $result->fetchOne();

        if ($id) {
            return $this->find($id);
        }

        return null;
    }
}