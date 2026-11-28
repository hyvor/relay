<?php

namespace App\Service\Stats;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

/**
 * Stats serve two different purposes, and count two different things:
 *
 * 1. stats_project: what users see. Counts send recipients by their current status.
 *    A day is the day the recipient was added (the project called the API to queue the email).
 *    Attempts are not considered. One recipient is counted exactly once, in one status.
 *
 * 2. stats_ip, stats_ip_project, stats_delivery_domain: internal monitoring and alerting.
 *    Counts attempts (send_attempt_recipients). A day is the day of the attempt.
 *    One recipient may have multiple attempts, and each attempt is counted.
 *
 * Both are re-counted from scratch for a day by rebuildDate(). The stats update job re-counts today
 * (and any day since the last run) plus the days flagged by markDateOutdated(), as the numbers of a
 * past day change when late events (async bounces, complaints, retries) arrive.
 */
class StatsService
{
    use ClockAwareTrait;

    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Flags the stats of the given day as outdated, so the next stats update re-counts that day.
     * Today is skipped, since it is always re-counted.
     */
    public function markDateOutdated(\DateTimeInterface $date): void
    {
        $statDate = $date->format('Y-m-d');

        if ($statDate >= $this->now()->format('Y-m-d')) {
            return;
        }

        $this->em->getConnection()->executeStatement(
            'INSERT INTO stats_outdated_dates (stat_date) VALUES (:statDate) ON CONFLICT DO NOTHING',
            ['statDate' => $statDate]
        );
    }

    /**
     * @return string[] Y-m-d dates flagged as outdated, not older than $minDate
     */
    public function getOutdatedDates(string $minDate): array
    {
        /** @var string[] $dates */
        $dates = $this->em->getConnection()->fetchFirstColumn(
            'SELECT stat_date FROM stats_outdated_dates WHERE stat_date >= :minDate',
            ['minDate' => $minDate]
        );

        return $dates;
    }

    /**
     * Re-counts all stats of the given day. Rows of the day are deleted and re-inserted.
     */
    public function rebuildDate(string $date): void
    {
        $nextDate = new \DateTimeImmutable($date)->modify('+1 day')->format('Y-m-d');

        $this->em->getConnection()->transactional(function (Connection $connection) use ($date, $nextDate) {
            // The flag is cleared first. If a late event flags the day again while this runs,
            // the flag stays, and the day is re-counted once more by the next run.
            $connection->executeStatement(
                'DELETE FROM stats_outdated_dates WHERE stat_date = :date',
                ['date' => $date]
            );

            $this->rebuildProjectStats($connection, $date, $nextDate);

            $this->createAttemptsTable($connection, $date, $nextDate);
            $this->rebuildIpStats($connection, $date, $nextDate);
            $this->rebuildIpProjectStats($connection, $date, $nextDate);
            $this->rebuildDeliveryDomainStats($connection, $date, $nextDate);

            $connection->executeStatement('DROP TABLE stats_attempts');
        });
    }

    /**
     * stats_project: send_recipients by status. The day is the day the recipient was added.
     *
     * - each recipient is counted in exactly one status (accepted does not include complained)
     * - bounces are split by send_recipients.bounce_reason
     * - rates are based on send_recipients (complained_rate is based on accepted + complained,
     *   as the server accepted a complained recipient before the complaint)
     */
    private function rebuildProjectStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_project WHERE stat_date = :date', ['date' => $date]);

        $connection->executeStatement(<<<SQL
            WITH
            recipients AS (
                SELECT
                    s.project_id,
                    COUNT(DISTINCT s.id) AS sends,
                    COUNT(sr.id) AS send_recipients,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'queued') AS queued,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'accepted') AS accepted,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'deferred') AS deferred,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'unknown') AS bounced_unknown,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'complained') AS complained,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'suppressed') AS suppressed,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'failed') AS failed
                FROM sends s
                JOIN send_recipients sr ON sr.send_id = s.id
                WHERE s.created_at >= :date AND s.created_at < :nextDate
                GROUP BY s.project_id
            )
            INSERT INTO stats_project (
                project_id, stat_date,
                sends, send_recipients, queued,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, complained, suppressed, failed,
                accepted_rate, deferred_rate, bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate,
                complained_rate, suppressed_rate, failed_rate
            )
            SELECT
                project_id, :date,
                sends, send_recipients, queued,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, complained, suppressed, failed,
                ROUND(accepted::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(deferred::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(bounced_recipient::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(bounced_infrastructure::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(bounced_unknown::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(complained::NUMERIC / NULLIF(accepted + complained, 0), 6),
                ROUND(suppressed::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(failed::NUMERIC / NULLIF(send_recipients, 0), 4)
            FROM recipients
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    /**
     * Attempts (send_attempt_recipients) of the day, pre-aggregated to the finest grain every attempt
     * stat needs: (project, ip, domain, recipient status, bounce reason), with the number of attempts
     * in `cnt`. A recipient attempted multiple times is counted once per attempt. The bounce reason
     * is only set for bounced attempts.
     *
     * This keeps the table small (it does not grow with the email volume), so the stats below
     * SUM(cnt) over thousands of rows instead of re-scanning millions.
     */
    private function createAttemptsTable(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS stats_attempts');
        $connection->executeStatement(<<<SQL
            CREATE TEMP TABLE stats_attempts (
                project_id BIGINT NOT NULL,
                ip_address_id BIGINT NOT NULL,
                domain TEXT NOT NULL,
                status TEXT NOT NULL,
                bounce_reason TEXT,
                cnt BIGINT NOT NULL
            ) ON COMMIT DROP
        SQL);

        $connection->executeStatement(<<<SQL
            INSERT INTO stats_attempts
            SELECT
                s.project_id,
                sa.ip_address_id,
                sa.domain,
                sar.status::TEXT,
                CASE WHEN sar.status = 'bounced' THEN sar.bounce_reason::TEXT END,
                COUNT(*)
            FROM send_attempt_recipients sar
            JOIN send_attempts sa ON sa.id = sar.send_attempt_id
            JOIN sends s ON s.id = sa.send_id
            WHERE sa.created_at >= :date AND sa.created_at < :nextDate
            GROUP BY 1, 2, 3, 4, 5
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    /**
     * stats_ip: attempts by status. The day is the day of the attempt.
     * Sends and send_recipients are informational (by the day of the send).
     */
    private function rebuildIpStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_ip WHERE stat_date = :date', ['date' => $date]);

        $connection->executeStatement(<<<SQL
            WITH
            submitted AS (
                SELECT
                    s.ip_address_id,
                    COUNT(DISTINCT s.id) AS sends,
                    COUNT(sr.id) AS send_recipients
                FROM sends s
                JOIN send_recipients sr ON sr.send_id = s.id
                WHERE s.created_at >= :date AND s.created_at < :nextDate AND s.ip_address_id IS NOT NULL
                GROUP BY s.ip_address_id
            ),
            attempts AS (
                SELECT ip_address_id, COUNT(id) AS send_attempts
                FROM send_attempts
                WHERE created_at >= :date AND created_at < :nextDate
                GROUP BY ip_address_id
            ),
            delivered AS (
                SELECT
                    ip_address_id,
                    SUM(cnt) AS attempted,
                    COALESCE(SUM(cnt) FILTER (WHERE status = 'accepted'), 0) AS accepted,
                    COALESCE(SUM(cnt) FILTER (WHERE status = 'deferred'), 0) AS deferred,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'recipient'), 0) AS bounced_recipient,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'infrastructure'), 0) AS bounced_infrastructure,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'unknown'), 0) AS bounced_unknown,
                    COALESCE(SUM(cnt) FILTER (WHERE status = 'failed'), 0) AS failed
                FROM stats_attempts
                GROUP BY ip_address_id
            ),
            ips AS (
                SELECT ip_address_id FROM submitted
                UNION SELECT ip_address_id FROM attempts
                UNION SELECT ip_address_id FROM delivered
            ),
            counts AS (
                SELECT
                    i.ip_address_id,
                    COALESCE(sub.sends, 0) AS sends,
                    COALESCE(sub.send_recipients, 0) AS send_recipients,
                    COALESCE(a.send_attempts, 0) AS send_attempts,
                    COALESCE(d.attempted, 0) AS attempted,
                    COALESCE(d.accepted, 0) AS accepted,
                    COALESCE(d.deferred, 0) AS deferred,
                    COALESCE(d.bounced_recipient, 0) AS bounced_recipient,
                    COALESCE(d.bounced_infrastructure, 0) AS bounced_infrastructure,
                    COALESCE(d.bounced_unknown, 0) AS bounced_unknown,
                    COALESCE(d.failed, 0) AS failed
                FROM ips i
                LEFT JOIN submitted sub ON sub.ip_address_id = i.ip_address_id
                LEFT JOIN attempts a ON a.ip_address_id = i.ip_address_id
                LEFT JOIN delivered d ON d.ip_address_id = i.ip_address_id
            )
            INSERT INTO stats_ip (
                ip_address_id, stat_date,
                sends, send_recipients, send_attempts, attempted,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, failed,
                accepted_rate, deferred_rate, bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate,
                failed_rate
            )
            SELECT
                ip_address_id, :date,
                sends, send_recipients, send_attempts, attempted,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, failed,
                ROUND(accepted::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(deferred::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_recipient::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_infrastructure::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_unknown::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(failed::NUMERIC / NULLIF(attempted, 0), 4)
            FROM counts
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    private function rebuildIpProjectStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_ip_project WHERE stat_date = :date', ['date' => $date]);

        $connection->executeStatement(<<<SQL
            WITH delivered AS (
                SELECT
                    ip_address_id,
                    project_id,
                    SUM(cnt) AS sent,
                    COALESCE(SUM(cnt) FILTER (WHERE status = 'accepted'), 0) AS accepted,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'recipient'), 0) AS bounced_recipient,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'infrastructure'), 0) AS bounced_infrastructure,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'unknown'), 0) AS bounced_unknown
                FROM stats_attempts
                GROUP BY ip_address_id, project_id
            )
            INSERT INTO stats_ip_project (
                ip_address_id, project_id, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown,
                bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate
            )
            SELECT
                ip_address_id, project_id, :date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown,
                ROUND(bounced_recipient::NUMERIC / NULLIF(sent, 0), 4),
                ROUND(bounced_infrastructure::NUMERIC / NULLIF(sent, 0), 4),
                ROUND(bounced_unknown::NUMERIC / NULLIF(sent, 0), 4)
            FROM delivered
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    private function rebuildDeliveryDomainStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_delivery_domain WHERE stat_date = :date', ['date' => $date]);

        $connection->executeStatement(<<<SQL
            WITH delivered AS (
                SELECT
                    project_id,
                    ip_address_id,
                    domain AS recipient_domain,
                    SUM(cnt) AS sent,
                    COALESCE(SUM(cnt) FILTER (WHERE status = 'accepted'), 0) AS accepted,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'recipient'), 0) AS bounced_recipient,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'infrastructure'), 0) AS bounced_infrastructure,
                    COALESCE(SUM(cnt) FILTER (WHERE bounce_reason = 'unknown'), 0) AS bounced_unknown
                FROM stats_attempts
                GROUP BY project_id, ip_address_id, domain
            )
            INSERT INTO stats_delivery_domain (
                project_id, ip_address_id, recipient_domain, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown
            )
            SELECT
                project_id, ip_address_id, recipient_domain, :date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown
            FROM delivered
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }
}
