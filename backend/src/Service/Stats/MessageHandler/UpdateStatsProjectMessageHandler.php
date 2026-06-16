<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Stats\Message\UpdateStatsProjectMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatsProjectMessageHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(UpdateStatsProjectMessage $message): void
    {
        $this->em->getConnection()->executeStatement(<<<SQL
            INSERT INTO stats_project (
                project_id, stat_date,
                sends, send_recipients, send_attempts,
                accepted, deferred, bounced_recipient, bounced_infrastructure, complained, suppressed, failed,
                accepted_rate, deferred_rate, bounced_recipient_rate, bounced_infrastructure_rate,
                complained_rate, suppressed_rate, failed_rate
            )
            SELECT
                s.project_id,
                CURRENT_DATE AS stat_date,
                COUNT(DISTINCT s.id) AS sends,
                COUNT(DISTINCT sr.id) AS send_recipients,
                COUNT(DISTINCT sa.id) AS send_attempts,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'accepted') AS accepted,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'deferred') AS deferred,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'complained') AS complained,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'suppressed') AS suppressed,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'failed') AS failed,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'accepted')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS accepted_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'deferred')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS deferred_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS bounced_recipient_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS bounced_infrastructure_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'complained')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS complained_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'suppressed')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS suppressed_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'failed')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS failed_rate
            FROM sends s
            JOIN send_recipients sr ON sr.send_id = s.id
            LEFT JOIN send_attempts sa ON sa.send_id = s.id AND sa.created_at::DATE = CURRENT_DATE
            WHERE s.created_at::DATE = CURRENT_DATE
            GROUP BY s.project_id, CURRENT_DATE
            ON CONFLICT (project_id, stat_date) DO UPDATE SET
                sends = EXCLUDED.sends,
                send_recipients = EXCLUDED.send_recipients,
                send_attempts = EXCLUDED.send_attempts,
                accepted = EXCLUDED.accepted,
                deferred = EXCLUDED.deferred,
                bounced_recipient = EXCLUDED.bounced_recipient,
                bounced_infrastructure = EXCLUDED.bounced_infrastructure,
                complained = EXCLUDED.complained,
                suppressed = EXCLUDED.suppressed,
                failed = EXCLUDED.failed,
                accepted_rate = EXCLUDED.accepted_rate,
                deferred_rate = EXCLUDED.deferred_rate,
                bounced_recipient_rate = EXCLUDED.bounced_recipient_rate,
                bounced_infrastructure_rate = EXCLUDED.bounced_infrastructure_rate,
                complained_rate = EXCLUDED.complained_rate,
                suppressed_rate = EXCLUDED.suppressed_rate,
                failed_rate = EXCLUDED.failed_rate
        SQL);
    }
}
