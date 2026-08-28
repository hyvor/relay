<?php

namespace App\Service\Stats;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class SudoAnalyticsService
{
    use ClockAwareTrait;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, int|float>
     */
    public function getStats(string $period = '24h'): array
    {
        $dateModifier = $this->getPeriodDateModifier($period);
        $startDate = $this->now()->modify($dateModifier);

        $result = $this->em->getConnection()->executeQuery(
            <<<SQL
            SELECT
                COALESCE(SUM(send_recipients), 0) AS total_send_recipients,
                COALESCE(SUM(bounced_recipient + bounced_infrastructure), 0) AS total_bounced,
                COALESCE(SUM(complained), 0) AS total_complained
            FROM stats_project
            WHERE stat_date >= :startDate
            SQL,
            ['startDate' => $startDate->format('Y-m-d')]
        );

        /** @var array<string, int|string> $globalStats */
        $globalStats = $result->fetchAssociative();

        $projectCount = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM projects'
        );
        $projectCount = is_scalar($projectCount) ? (int) $projectCount : 0;

        $totalRecipients = (int) $globalStats['total_send_recipients'];
        $totalBounced = (int) $globalStats['total_bounced'];
        $totalComplained = (int) $globalStats['total_complained'];

        return [
            'project_count' => $projectCount,
            'sends' => $totalRecipients,
            'bounce_rate' => $totalRecipients > 0 ? round($totalBounced / $totalRecipients, 4) : 0.0,
            'complaint_rate' => $totalRecipients > 0 ? round($totalComplained / $totalRecipients, 4) : 0.0,
        ];
    }

    private function getPeriodDateModifier(string $period): string
    {
        return match ($period) {
            '24h' => '-1 day',
            '7d' => '-7 days',
            '30d' => '-30 days',
            default => '-1 day',
        };
    }
}
