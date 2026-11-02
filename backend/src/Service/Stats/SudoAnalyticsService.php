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
                COUNT(DISTINCT project_id) AS project_count,
                COALESCE(SUM(send_recipients), 0) AS total_send_recipients,
                COALESCE(SUM(accepted), 0) AS total_accepted,
                COALESCE(SUM(bounced_recipient + bounced_infrastructure + bounced_unknown), 0) AS total_bounced,
                COALESCE(SUM(complained), 0) AS total_complained
            FROM stats_project
            WHERE stat_date >= :startDate
            SQL,
            ['startDate' => $startDate->format('Y-m-d')]
        );

        /** @var array<string, int|string> $globalStats */
        $globalStats = $result->fetchAssociative();

        $totalRecipients = (int) $globalStats['total_send_recipients'];
        $totalAccepted = (int) $globalStats['total_accepted'];
        $totalBounced = (int) $globalStats['total_bounced'];
        $totalComplained = (int) $globalStats['total_complained'];

        return [
            'project_count' => (int) $globalStats['project_count'],
            'sends' => $totalRecipients,
            'bounce_rate' => $totalRecipients > 0 ? round($totalBounced / $totalRecipients, 4) : 0.0,
            'complaint_rate' => $totalAccepted > 0 ? round($totalComplained / $totalAccepted, 4) : 0.0,
        ];
    }

    private function getPeriodDateModifier(string $period): string
    {
        return match ($period) {
            '7d' => '-6 days',
            '30d' => '-29 days',
            default => 'today',
        };
    }
}
