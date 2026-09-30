<?php

namespace App\Service\Stats;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class StatsService
{
    use ClockAwareTrait;

    public const string GOOGLE_RECIPIENT_DOMAIN = 'gmail.com';

    private const string REDACTED_CTE = <<<SQL
        redacted AS (
            SELECT project_id, ip_address_id
            FROM send_feedback
            WHERE type = 'complaint'
            AND send_recipient_id IS NULL
            AND stat_date = :date
        )
    SQL;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return int[]
     */
    public function getUnprocessedFeedbackIds(): array
    {
        /** @var int[] $ids */
        $ids = $this->em->getConnection()->fetchFirstColumn(
            'SELECT id FROM send_feedback WHERE processed_at IS NULL'
        );

        return $ids;
    }

    /**
     * @return int[]
     */
    public function getUnprocessedProviderMetricIds(): array
    {
        /** @var int[] $ids */
        $ids = $this->em->getConnection()->fetchFirstColumn(
            'SELECT id FROM provider_metrics WHERE processed_at IS NULL'
        );

        return $ids;
    }

    /**
     * @param int[] $feedbackIds
     * @return string[]
     */
    public function assignFeedbackStatDates(array $feedbackIds): array
    {
        /** @var string[] $dates */
        $dates = $this->em->getConnection()->fetchFirstColumn(
            <<<SQL
            WITH updated AS (
                UPDATE send_feedback sf
                SET stat_date = (
                    SELECT MIN(sa.created_at)::DATE
                    FROM send_attempts sa
                    JOIN send_attempt_recipients sar ON sar.send_attempt_id = sa.id
                    WHERE sa.send_id = sf.send_id
                    AND sar.recipient_status = 'accepted'
                    AND (sf.send_recipient_id IS NULL OR sar.send_recipient_id = sf.send_recipient_id)
                )
                WHERE sf.id IN (:ids)
                RETURNING sf.stat_date
            )
            SELECT DISTINCT stat_date FROM updated WHERE stat_date IS NOT NULL
            SQL,
            ['ids' => $feedbackIds],
            ['ids' => ArrayParameterType::INTEGER]
        );

        return $dates;
    }

    /**
     * @param int[] $feedbackIds
     */
    public function markFeedbackProcessed(array $feedbackIds): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE send_feedback SET processed_at = :now WHERE id IN (:ids)',
            ['now' => $this->now()->format('Y-m-d H:i:sP'), 'ids' => $feedbackIds],
            ['ids' => ArrayParameterType::INTEGER]
        );
    }

    /**
     * @param int[] $providerMetricIds
     * @return string[]
     */
    public function getProviderMetricDates(array $providerMetricIds): array
    {
        /** @var string[] $dates */
        $dates = $this->em->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT metric_date FROM provider_metrics WHERE id IN (:ids)',
            ['ids' => $providerMetricIds],
            ['ids' => ArrayParameterType::INTEGER]
        );

        return $dates;
    }

    /**
     * @param int[] $providerMetricIds
     */
    public function markProviderMetricsProcessed(array $providerMetricIds): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE provider_metrics SET processed_at = :now WHERE id IN (:ids)',
            ['now' => $this->now()->format('Y-m-d H:i:sP'), 'ids' => $providerMetricIds],
            ['ids' => ArrayParameterType::INTEGER]
        );
    }

    public function rebuildDate(string $date): void
    {
        $nextDate = new \DateTimeImmutable($date)->modify('+1 day')->format('Y-m-d');

        $this->em->getConnection()->transactional(function (Connection $connection) use ($date, $nextDate) {
            $this->createAttemptedTable($connection, $date, $nextDate);
            $this->rebuildProjectStats($connection, $date, $nextDate);
            $this->rebuildIpStats($connection, $date, $nextDate);
            $this->rebuildIpProjectStats($connection, $date, $nextDate);
            $this->rebuildDeliveryDomainStats($connection, $date, $nextDate);

            $connection->executeStatement('DROP TABLE stats_attempted');
        });
    }

    private function createAttemptedTable(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS stats_attempted');
        $connection->executeStatement(<<<SQL
            CREATE TEMP TABLE stats_attempted (
                project_id BIGINT NOT NULL,
                ip_address_id BIGINT NOT NULL,
                domain TEXT NOT NULL,
                send_recipient_id BIGINT NOT NULL,
                recipient_status TEXT NOT NULL,
                bounce_reason TEXT,
                complained BOOLEAN NOT NULL
            ) ON COMMIT DROP
        SQL);

        $connection->executeStatement(<<<SQL
            INSERT INTO stats_attempted
            WITH
            day_attempts AS (
                SELECT
                    s.project_id,
                    sa.ip_address_id,
                    sa.domain,
                    sar.send_recipient_id,
                    sar.recipient_status::TEXT AS recipient_status,
                    sar.bounce_reason::TEXT AS bounce_reason
                FROM send_attempt_recipients sar
                JOIN send_attempts sa ON sa.id = sar.send_attempt_id
                JOIN sends s ON s.id = sa.send_id
                WHERE sa.created_at >= :date AND sa.created_at < :nextDate
            ),
            feedback AS (
                SELECT
                    sf.send_recipient_id,
                    (ARRAY_AGG(sf.detail ORDER BY sf.id DESC) FILTER (WHERE sf.type = 'bounce'))[1] AS bounce_detail,
                    BOOL_OR(sf.type = 'complaint') AS complained
                FROM send_feedback sf
                WHERE sf.send_recipient_id IN (
                    SELECT send_recipient_id FROM day_attempts WHERE recipient_status = 'accepted'
                )
                GROUP BY sf.send_recipient_id
            )
            SELECT
                d.project_id,
                d.ip_address_id,
                d.domain,
                d.send_recipient_id,
                d.recipient_status,
                CASE
                    WHEN d.recipient_status = 'bounced' THEN d.bounce_reason
                    WHEN d.recipient_status = 'accepted' THEN f.bounce_detail
                END,
                d.recipient_status = 'accepted' AND COALESCE(f.complained, FALSE)
            FROM day_attempts d
            LEFT JOIN feedback f ON f.send_recipient_id = d.send_recipient_id
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    private function rebuildProjectStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_project WHERE stat_date = :date', ['date' => $date]);

        $redacted = self::REDACTED_CTE;

        $connection->executeStatement(<<<SQL
            WITH
            $redacted,
            submitted AS (
                SELECT
                    s.project_id,
                    COUNT(DISTINCT s.id) AS sends,
                    COUNT(sr.id) AS send_recipients,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'suppressed') AS suppressed
                FROM sends s
                JOIN send_recipients sr ON sr.send_id = s.id
                WHERE s.created_at >= :date AND s.created_at < :nextDate
                GROUP BY s.project_id
            ),
            attempts AS (
                SELECT s.project_id, COUNT(sa.id) AS send_attempts
                FROM send_attempts sa
                JOIN sends s ON s.id = sa.send_id
                WHERE sa.created_at >= :date AND sa.created_at < :nextDate
                GROUP BY s.project_id
            ),
            delivered AS (
                SELECT
                    project_id,
                    COUNT(DISTINCT send_recipient_id) AS attempted,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'accepted') AS accepted,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'deferred') AS deferred,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'recipient') AS bounced_recipient,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'infrastructure') AS bounced_infrastructure,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'unknown') AS bounced_unknown,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'failed') AS failed,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE complained) AS complained
                FROM stats_attempted
                GROUP BY project_id
            ),
            redacted_complaints AS (
                SELECT project_id, COUNT(*) AS complained
                FROM redacted
                GROUP BY project_id
            ),
            projects AS (
                SELECT project_id FROM submitted
                UNION SELECT project_id FROM attempts
                UNION SELECT project_id FROM delivered
                UNION SELECT project_id FROM redacted_complaints
            ),
            counts AS (
                SELECT
                    p.project_id,
                    COALESCE(sub.sends, 0) AS sends,
                    COALESCE(sub.send_recipients, 0) AS send_recipients,
                    COALESCE(sub.suppressed, 0) AS suppressed,
                    COALESCE(a.send_attempts, 0) AS send_attempts,
                    COALESCE(d.attempted, 0) AS attempted,
                    COALESCE(d.accepted, 0) AS accepted,
                    COALESCE(d.deferred, 0) AS deferred,
                    COALESCE(d.bounced_recipient, 0) AS bounced_recipient,
                    COALESCE(d.bounced_infrastructure, 0) AS bounced_infrastructure,
                    COALESCE(d.bounced_unknown, 0) AS bounced_unknown,
                    COALESCE(d.failed, 0) AS failed,
                    COALESCE(d.complained, 0) + COALESCE(rc.complained, 0) AS complained
                FROM projects p
                LEFT JOIN submitted sub ON sub.project_id = p.project_id
                LEFT JOIN attempts a ON a.project_id = p.project_id
                LEFT JOIN delivered d ON d.project_id = p.project_id
                LEFT JOIN redacted_complaints rc ON rc.project_id = p.project_id
            )
            INSERT INTO stats_project (
                project_id, stat_date,
                sends, send_recipients, send_attempts,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, complained, suppressed, failed,
                accepted_rate, deferred_rate, bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate,
                complained_rate, suppressed_rate, failed_rate
            )
            SELECT
                project_id, :date,
                sends, send_recipients, send_attempts,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, complained, suppressed, failed,
                ROUND(accepted::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(deferred::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_recipient::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_infrastructure::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_unknown::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(complained::NUMERIC / NULLIF(accepted, 0), 6),
                ROUND(suppressed::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(failed::NUMERIC / NULLIF(attempted, 0), 4)
            FROM counts
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    private function rebuildIpStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_ip WHERE stat_date = :date', ['date' => $date]);

        $redacted = self::REDACTED_CTE;

        $connection->executeStatement(<<<SQL
            WITH
            $redacted,
            submitted AS (
                SELECT
                    s.ip_address_id,
                    COUNT(DISTINCT s.id) AS sends,
                    COUNT(sr.id) AS send_recipients,
                    COUNT(sr.id) FILTER (WHERE sr.status = 'suppressed') AS suppressed
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
                    COUNT(DISTINCT send_recipient_id) AS attempted,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'accepted') AS accepted,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'deferred') AS deferred,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'recipient') AS bounced_recipient,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'infrastructure') AS bounced_infrastructure,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'unknown') AS bounced_unknown,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'failed') AS failed,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE complained) AS complained
                FROM stats_attempted
                GROUP BY ip_address_id
            ),
            redacted_complaints AS (
                SELECT ip_address_id, COUNT(*) AS complained
                FROM redacted
                WHERE ip_address_id IS NOT NULL
                GROUP BY ip_address_id
            ),
            ips AS (
                SELECT ip_address_id FROM submitted
                UNION SELECT ip_address_id FROM attempts
                UNION SELECT ip_address_id FROM delivered
                UNION SELECT ip_address_id FROM redacted_complaints
            ),
            counts AS (
                SELECT
                    i.ip_address_id,
                    COALESCE(sub.sends, 0) AS sends,
                    COALESCE(sub.send_recipients, 0) AS send_recipients,
                    COALESCE(sub.suppressed, 0) AS suppressed,
                    COALESCE(a.send_attempts, 0) AS send_attempts,
                    COALESCE(d.attempted, 0) AS attempted,
                    COALESCE(d.accepted, 0) AS accepted,
                    COALESCE(d.deferred, 0) AS deferred,
                    COALESCE(d.bounced_recipient, 0) AS bounced_recipient,
                    COALESCE(d.bounced_infrastructure, 0) AS bounced_infrastructure,
                    COALESCE(d.bounced_unknown, 0) AS bounced_unknown,
                    COALESCE(d.failed, 0) AS failed,
                    COALESCE(d.complained, 0) + COALESCE(rc.complained, 0) AS complained
                FROM ips i
                LEFT JOIN submitted sub ON sub.ip_address_id = i.ip_address_id
                LEFT JOIN attempts a ON a.ip_address_id = i.ip_address_id
                LEFT JOIN delivered d ON d.ip_address_id = i.ip_address_id
                LEFT JOIN redacted_complaints rc ON rc.ip_address_id = i.ip_address_id
            )
            INSERT INTO stats_ip (
                ip_address_id, stat_date,
                sends, send_recipients, send_attempts,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, complained, suppressed, failed,
                accepted_rate, deferred_rate, bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate,
                complained_rate, suppressed_rate, failed_rate
            )
            SELECT
                ip_address_id, :date,
                sends, send_recipients, send_attempts,
                accepted, deferred, bounced_recipient, bounced_infrastructure, bounced_unknown, complained, suppressed, failed,
                ROUND(accepted::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(deferred::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_recipient::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_infrastructure::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(bounced_unknown::NUMERIC / NULLIF(attempted, 0), 4),
                ROUND(complained::NUMERIC / NULLIF(accepted, 0), 6),
                ROUND(suppressed::NUMERIC / NULLIF(send_recipients, 0), 4),
                ROUND(failed::NUMERIC / NULLIF(attempted, 0), 4)
            FROM counts
        SQL, ['date' => $date, 'nextDate' => $nextDate]);
    }

    private function rebuildIpProjectStats(Connection $connection, string $date, string $nextDate): void
    {
        $connection->executeStatement('DELETE FROM stats_ip_project WHERE stat_date = :date', ['date' => $date]);

        $redacted = self::REDACTED_CTE;

        $connection->executeStatement(<<<SQL
            WITH
            $redacted,
            delivered AS (
                SELECT
                    ip_address_id,
                    project_id,
                    COUNT(DISTINCT send_recipient_id) AS sent,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'accepted') AS accepted,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'recipient') AS bounced_recipient,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'infrastructure') AS bounced_infrastructure,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'unknown') AS bounced_unknown,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE complained) AS complained
                FROM stats_attempted
                GROUP BY ip_address_id, project_id
            ),
            redacted_complaints AS (
                SELECT ip_address_id, project_id, COUNT(*) AS complained
                FROM redacted
                WHERE ip_address_id IS NOT NULL
                GROUP BY ip_address_id, project_id
            ),
            counts AS (
                SELECT
                    COALESCE(d.ip_address_id, rc.ip_address_id) AS ip_address_id,
                    COALESCE(d.project_id, rc.project_id) AS project_id,
                    COALESCE(d.sent, 0) AS sent,
                    COALESCE(d.accepted, 0) AS accepted,
                    COALESCE(d.bounced_recipient, 0) AS bounced_recipient,
                    COALESCE(d.bounced_infrastructure, 0) AS bounced_infrastructure,
                    COALESCE(d.bounced_unknown, 0) AS bounced_unknown,
                    COALESCE(d.complained, 0) + COALESCE(rc.complained, 0) AS complained
                FROM delivered d
                FULL JOIN redacted_complaints rc
                    ON rc.ip_address_id = d.ip_address_id AND rc.project_id = d.project_id
            )
            INSERT INTO stats_ip_project (
                ip_address_id, project_id, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown, complained,
                bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate, complained_rate
            )
            SELECT
                ip_address_id, project_id, :date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown, complained,
                ROUND(bounced_recipient::NUMERIC / NULLIF(sent, 0), 4),
                ROUND(bounced_infrastructure::NUMERIC / NULLIF(sent, 0), 4),
                ROUND(bounced_unknown::NUMERIC / NULLIF(sent, 0), 4),
                ROUND(complained::NUMERIC / NULLIF(accepted, 0), 6)
            FROM counts
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
                    COUNT(DISTINCT send_recipient_id) AS sent,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE recipient_status = 'accepted') AS accepted,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'recipient') AS bounced_recipient,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'infrastructure') AS bounced_infrastructure,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE bounce_reason = 'unknown') AS bounced_unknown,
                    COUNT(DISTINCT send_recipient_id) FILTER (WHERE complained) AS complained
                FROM stats_attempted
                GROUP BY project_id, ip_address_id, domain
            ),
            google AS (
                SELECT DISTINCT ON (project_id, ip_address_id)
                    project_id,
                    ip_address_id,
                    :googleRecipientDomain AS recipient_domain,
                    value
                FROM provider_metrics
                WHERE source = 'google' AND metric_date = :date
                ORDER BY project_id, ip_address_id, id DESC
            )
            INSERT INTO stats_delivery_domain (
                project_id, ip_address_id, recipient_domain, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown, complained,
                complained_rate
            )
            SELECT
                COALESCE(d.project_id, g.project_id),
                COALESCE(d.ip_address_id, g.ip_address_id),
                COALESCE(d.recipient_domain, g.recipient_domain),
                :date,
                COALESCE(d.sent, 0),
                COALESCE(d.accepted, 0),
                COALESCE(d.bounced_recipient, 0),
                COALESCE(d.bounced_infrastructure, 0),
                COALESCE(d.bounced_unknown, 0),
                COALESCE(d.complained, 0),
                COALESCE(g.value, ROUND(d.complained::NUMERIC / NULLIF(d.accepted, 0), 6))
            FROM delivered d
            FULL JOIN google g
                ON g.project_id = d.project_id
                AND g.ip_address_id = d.ip_address_id
                AND g.recipient_domain = d.recipient_domain
        SQL, ['date' => $date, 'nextDate' => $nextDate, 'googleRecipientDomain' => self::GOOGLE_RECIPIENT_DOMAIN]);
    }
}
