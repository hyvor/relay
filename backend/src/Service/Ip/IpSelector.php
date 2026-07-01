<?php

namespace App\Service\Ip;

use App\Entity\IpAddress;
use App\Entity\Queue;
use App\Entity\Type\WarmupStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class IpSelector
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function selectForQueue(Queue $queue, int $recipientCount = 1): ?IpAddress
    {
        /** @var IpAddress[] $ips */
        $ips = $this->em->createQuery('
                SELECT ip, ws
                FROM App\Entity\IpAddress ip
                LEFT JOIN ip.warmupSchedules ws
                WITH ws.created_at = (
                    SELECT MAX(ws2.created_at)
                    FROM App\Entity\WarmupSchedule ws2
                    WHERE ws2.ip_address = ip.id
                )
                WHERE ip.queue = :queue
            ')
            ->setParameter('queue', $queue)
            ->getResult();

        if (empty($ips)) {
            return null;
        }

        shuffle($ips);

        $conn = $this->em->getConnection();

        foreach ($ips as $ip) {
            $warmup = $ip->getCurrentWarmupSchedule();

            if ($warmup?->isWarmingUp()) {
                $rows = $conn->executeStatement(
                    'UPDATE warmup_schedules
                     SET warmup_sent_today = warmup_sent_today + :count
                     WHERE ip_address_id = :id
                       AND warmup_status = :status
                       AND warmup_sent_today + :count <= warmup_max_today',
                    [
                        'count' => $recipientCount,
                        'id' => $ip->getId(),
                        'status' => WarmupStatus::WARMING->value,
                    ]
                );

                if ($rows > 0) {
                    return $ip;
                }
			} else {
				return $ip;
			}
        }

        return null;
    }
}
