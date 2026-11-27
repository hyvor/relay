<?php

namespace App\Service\Cloud\MeteredUsage;

use App\Service\App\MessageTransport;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Billing\BillingInterface;

class MeteredUsageService
{

    public function __construct(
        private EntityManagerInterface $em,
        private BillingInterface $billing,
    ) {
    }

    /**
     * @return array<int, int> organization_id => amount
     */
    public function getDailyUsageByOrganization(\DateTimeImmutable $day): array
    {
        $start = $day->setTime(0, 0);
        $end = $start->modify('+1 day');

        /** @var array<array{organization_id: int|string, amount: int|string}> $rows */
        $rows = $this->em->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT p.organization_id, COUNT(sr.id) AS amount
            FROM sends s
            JOIN projects p ON p.id = s.project_id
            JOIN send_recipients sr ON sr.send_id = s.id
            WHERE s.created_at >= :start
                AND s.created_at < :end
                AND p.organization_id IS NOT NULL
            GROUP BY p.organization_id
            ORDER BY p.organization_id
            SQL,
            [
                'start' => $start->format('Y-m-d H:i:s'),
                'end' => $end->format('Y-m-d H:i:s')
            ]
        );

        $usage = [];
        foreach ($rows as $row) {
            $usage[(int) $row['organization_id']] = (int) $row['amount'];
        }

        return $usage;
    }

    /**
     * @return int number of organizations reported
     */
    public function reportDailyUsage(\DateTimeImmutable $day, bool $force = false): int
    {
        $usage = $this->getDailyUsageByOrganization($day);

        foreach ($usage as $organizationId => $amount) {
            $this->billing->recordMeteredUsage(
                $organizationId,
                $amount,
                $day,
                force: $force,
                async: true,
                transport: MessageTransport::CLOUD,
            );
        }

        return count($usage);
    }

}
