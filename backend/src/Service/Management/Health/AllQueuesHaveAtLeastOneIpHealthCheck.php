<?php

namespace App\Service\Management\Health;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

class AllQueuesHaveAtLeastOneIpHealthCheck extends HealthCheckAbstract
{

    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function check(): bool
    {

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('name', 'name');

        /** @var array<array{name: string}> $queuesWithoutIp */
        $queuesWithoutIp = $this->em->createNativeQuery(<<<SQL
        SELECT queues.name FROM queues
        WHERE (
            SELECT COUNT(ip_addresses.id) 
            FROM ip_addresses
            WHERE 
                ip_addresses.queue_id = queues.id AND
                ip_addresses.is_enabled = true AND
                ip_addresses.is_available = true
        ) = 0
        SQL, $rsm)
            ->getArrayResult();

        if (count($queuesWithoutIp) === 0) {
            return true;
        }

        $this->setData([
            'queues_without_ip' => array_map(fn($queue) => $queue['name'], $queuesWithoutIp),
        ]);

        return false;

    }

}
