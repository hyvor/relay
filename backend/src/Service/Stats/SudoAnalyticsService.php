<?php

namespace App\Service\Stats;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class SudoAnalyticsService
{
    use ClockAwareTrait;

    private const int TOP_LIMIT = 10;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $period = 'today'): array
    {
        $days = $this->getPeriodDays($period);
        $today = $this->now()->setTime(0, 0);
        $start = $today->modify('-' . ($days - 1) . ' days');
        $previousStart = $start->modify("-$days days");
        $previousEnd = $start->modify('-1 day');

        $totals = $this->getTotals($start->format('Y-m-d'), $today->format('Y-m-d'));
        $previous = $this->getTotals($previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d'));

        return [
            // kept at the top level for backward compatibility
            'project_count' => $totals['projects'],
            'sends' => $totals['send_recipients'],
            'bounce_rate' => $totals['rates']['bounce'],
            'complaint_rate' => $totals['rates']['complaint'],

            'period' => $period,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $today->format('Y-m-d'),
            'totals' => $totals,
            'previous_totals' => $previous,
            'daily' => $this->getDaily($today->modify('-' . (max($days, 7) - 1) . ' days'), $today),
            'ips' => $this->getIps($start->format('Y-m-d'), $today->format('Y-m-d')),
            'projects' => $this->getProjects($start->format('Y-m-d'), $today->format('Y-m-d')),
            'domains' => $this->getDomains($start->format('Y-m-d'), $today->format('Y-m-d')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getTotals(string $from, string $to): array
    {
        /** @var array<string, int|string> $row */
        $row = $this->em->getConnection()->executeQuery(
            <<<SQL
            SELECT
                COUNT(DISTINCT project_id) FILTER (WHERE send_recipients > 0) AS projects,
                COALESCE(SUM(sends), 0) AS sends,
                COALESCE(SUM(send_recipients), 0) AS send_recipients,
                COALESCE(SUM(accepted), 0) AS accepted,
                COALESCE(SUM(deferred), 0) AS deferred,
                COALESCE(SUM(bounced_recipient), 0) AS bounced_recipient,
                COALESCE(SUM(bounced_infrastructure), 0) AS bounced_infrastructure,
                COALESCE(SUM(bounced_unknown), 0) AS bounced_unknown,
                COALESCE(SUM(complained), 0) AS complained,
                COALESCE(SUM(suppressed), 0) AS suppressed,
                COALESCE(SUM(failed), 0) AS failed
            FROM stats_project
            WHERE stat_date BETWEEN :from AND :to
            SQL,
            ['from' => $from, 'to' => $to]
        )->fetchAssociative();

        $counts = array_map('intval', $row);
        $counts['bounced'] = $counts['bounced_recipient'] + $counts['bounced_infrastructure'] + $counts['bounced_unknown'];
        $counts['rates'] = $this->rates($counts);

        return $counts;
    }

    /**
     * Project stats count send recipients by their current status: rates are based on send_recipients,
     * and an accepted count does not include complained recipients.
     * IP and domain stats count attempts: rates are based on attempted, and accepted includes complained.
     *
     * @param array<string, int> $c
     * @return array<string, float>
     */
    private function rates(array $c, bool $byAttempts = false): array
    {
        $base = $byAttempts ? ($c['attempted'] ?? 0) : ($c['send_recipients'] ?? 0);
        $complaintBase = $byAttempts ? $c['accepted'] : $c['accepted'] + $c['complained'];
        $bounced = $c['bounced']
            ?? ($c['bounced_recipient'] + $c['bounced_infrastructure'] + $c['bounced_unknown']);

        $r = fn (int $n, int $d, int $p = 4): float => $d > 0 ? round($n / $d, $p) : 0.0;

        return [
            'accepted' => $r($c['accepted'], $base),
            'deferred' => $r($c['deferred'] ?? 0, $base),
            'bounce' => $r($bounced, $base),
            'bounce_recipient' => $r($c['bounced_recipient'], $base),
            'bounce_infrastructure' => $r($c['bounced_infrastructure'], $base),
            'bounce_unknown' => $r($c['bounced_unknown'], $base),
            'complaint' => $r($c['complained'], $complaintBase, 6),
            'suppressed' => $r($c['suppressed'] ?? 0, $c['send_recipients'] ?? 0),
            'failed' => $r($c['failed'] ?? 0, $base),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getDaily(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        /** @var list<array<string, int|string>> $rows */
        $rows = $this->em->getConnection()->executeQuery(
            <<<SQL
            SELECT
                stat_date::TEXT AS date,
                SUM(send_recipients) AS send_recipients,
                SUM(accepted) AS accepted,
                SUM(deferred) AS deferred,
                SUM(bounced_recipient) AS bounced_recipient,
                SUM(bounced_infrastructure) AS bounced_infrastructure,
                SUM(bounced_unknown) AS bounced_unknown,
                SUM(complained) AS complained,
                SUM(suppressed) AS suppressed,
                SUM(failed) AS failed
            FROM stats_project
            WHERE stat_date BETWEEN :from AND :to
            GROUP BY stat_date
            SQL,
            ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]
        )->fetchAllAssociative();

        $byDate = [];
        foreach ($rows as $row) {
            $date = (string) $row['date'];
            unset($row['date']);
            $counts = array_map('intval', $row);
            $counts['bounced'] = $counts['bounced_recipient'] + $counts['bounced_infrastructure'] + $counts['bounced_unknown'];
            $byDate[$date] = ['date' => $date] + $counts + ['rates' => $this->rates($counts)];
        }

        // fill gaps with zeros so charts have a continuous axis
        $daily = [];
        $cursor = \DateTimeImmutable::createFromInterface($from);
        while ($cursor <= $to) {
            $date = $cursor->format('Y-m-d');
            $daily[] = $byDate[$date] ?? [
                'date' => $date,
                'send_recipients' => 0, 'accepted' => 0, 'deferred' => 0,
                'bounced_recipient' => 0, 'bounced_infrastructure' => 0, 'bounced_unknown' => 0,
                'complained' => 0, 'suppressed' => 0, 'failed' => 0, 'bounced' => 0,
                'rates' => $this->rates([
                    'accepted' => 0, 'deferred' => 0, 'bounced_recipient' => 0,
                    'bounced_infrastructure' => 0, 'bounced_unknown' => 0, 'complained' => 0,
                    'suppressed' => 0, 'failed' => 0, 'send_recipients' => 0,
                ]),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $daily;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getIps(string $from, string $to): array
    {
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->em->getConnection()->executeQuery(
            <<<SQL
            SELECT
                i.id,
                i.ip_address AS ip,
                srv.hostname AS server,
                i.is_ptr_forward_valid AND i.is_ptr_reverse_valid AS ptr_valid,
                COALESCE(SUM(s.send_recipients), 0) AS send_recipients,
                COALESCE(SUM(s.send_attempts), 0) AS send_attempts,
                COALESCE(SUM(s.attempted), 0) AS attempted,
                COALESCE(SUM(s.accepted), 0) AS accepted,
                COALESCE(SUM(s.deferred), 0) AS deferred,
                COALESCE(SUM(s.bounced_recipient), 0) AS bounced_recipient,
                COALESCE(SUM(s.bounced_infrastructure), 0) AS bounced_infrastructure,
                COALESCE(SUM(s.bounced_unknown), 0) AS bounced_unknown,
                COALESCE(SUM(s.complained), 0) AS complained,
                COALESCE(SUM(s.suppressed), 0) AS suppressed,
                COALESCE(SUM(s.failed), 0) AS failed
            FROM ip_addresses i
            JOIN servers srv ON srv.id = i.server_id
            LEFT JOIN stats_ip s ON s.ip_address_id = i.id AND s.stat_date BETWEEN :from AND :to
            GROUP BY i.id, srv.hostname
            ORDER BY send_recipients DESC, i.id
            SQL,
            ['from' => $from, 'to' => $to]
        )->fetchAllAssociative();

        return array_map(function (array $row): array {
            $id = (int) $row['id'];
            $ip = (string) $row['ip'];
            $server = (string) $row['server'];
            $ptrValid = (bool) $row['ptr_valid'];
            unset($row['id'], $row['ip'], $row['server'], $row['ptr_valid']);

            return ['id' => $id, 'ip' => $ip, 'server' => $server, 'ptr_valid' => $ptrValid]
                + $this->withDerived($row, byAttempts: true);
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getProjects(string $from, string $to): array
    {
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->em->getConnection()->executeQuery(
            <<<SQL
            SELECT
                p.id,
                p.name,
                SUM(s.sends) AS sends,
                SUM(s.send_recipients) AS send_recipients,
                SUM(s.accepted) AS accepted,
                SUM(s.deferred) AS deferred,
                SUM(s.bounced_recipient) AS bounced_recipient,
                SUM(s.bounced_infrastructure) AS bounced_infrastructure,
                SUM(s.bounced_unknown) AS bounced_unknown,
                SUM(s.complained) AS complained,
                SUM(s.suppressed) AS suppressed,
                SUM(s.failed) AS failed
            FROM stats_project s
            JOIN projects p ON p.id = s.project_id
            WHERE s.stat_date BETWEEN :from AND :to
            GROUP BY p.id, p.name
            HAVING SUM(s.send_recipients) > 0
            ORDER BY SUM(s.send_recipients) DESC
            SQL,
            ['from' => $from, 'to' => $to]
        )->fetchAllAssociative();

        $projects = array_map(function (array $row): array {
            $id = (int) $row['id'];
            $name = (string) $row['name'];
            unset($row['id'], $row['name']);

            return ['id' => $id, 'name' => $name] + $this->withDerived($row);
        }, $rows);

        return array_slice($projects, 0, 50);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getDomains(string $from, string $to): array
    {
        $limit = self::TOP_LIMIT;
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $this->em->getConnection()->executeQuery(
            <<<SQL
            SELECT
                recipient_domain AS domain,
                SUM(sent) AS attempted,
                SUM(accepted) AS accepted,
                SUM(bounced_recipient) AS bounced_recipient,
                SUM(bounced_infrastructure) AS bounced_infrastructure,
                SUM(bounced_unknown) AS bounced_unknown,
                SUM(complained) AS complained
            FROM stats_delivery_domain
            WHERE project_id IS NULL AND ip_address_id IS NULL
            AND stat_date BETWEEN :from AND :to
            GROUP BY recipient_domain
            ORDER BY SUM(sent) DESC
            LIMIT $limit
            SQL,
            ['from' => $from, 'to' => $to]
        )->fetchAllAssociative();

        return array_map(function (array $row): array {
            $domain = (string) $row['domain'];
            unset($row['domain']);

            return ['domain' => $domain] + $this->withDerived($row, byAttempts: true);
        }, $rows);
    }

    /**
     * @param array<string, int|string|null> $row
     * @return array<string, mixed>
     */
    private function withDerived(array $row, bool $byAttempts = false): array
    {
        $counts = array_map(fn ($v) => (int) $v, $row);
        $counts += ['deferred' => 0, 'suppressed' => 0, 'failed' => 0, 'send_recipients' => 0];
        $counts['bounced'] = $counts['bounced_recipient'] + $counts['bounced_infrastructure'] + $counts['bounced_unknown'];

        return $counts + ['rates' => $this->rates($counts, $byAttempts)];
    }

    private function getPeriodDays(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            default => 1,
        };
    }
}
