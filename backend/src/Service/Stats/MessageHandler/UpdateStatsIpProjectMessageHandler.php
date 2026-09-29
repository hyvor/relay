<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Stats\Message\UpdateStatsIpProjectMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatsIpProjectMessageHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(UpdateStatsIpProjectMessage $message): void
    {
        $statDate = $message->forLastDay
            ? (new \DateTimeImmutable('yesterday'))->format('Y-m-d')
            : (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->em->getConnection()->executeStatement(<<<SQL
            INSERT INTO stats_ip_project (
                ip_address_id, project_id, stat_date,
                sent, bounced_recipient, bounced_infrastructure, bounced_unknown, complained,
                bounced_recipient_rate, bounced_infrastructure_rate, bounced_unknown_rate, complained_rate
            )
            SELECT
                ia.id,
                s.project_id,
                :stat_date AS stat_date,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status IN ('accepted', 'deferred', 'bounced', 'failed', 'suppressed')) AS sent,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'unknown') AS bounced_unknown,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'complained') AS complained,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS bounced_recipient_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS bounced_infrastructure_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'unknown')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS bounced_unknown_rate,
                ROUND(
                    COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'complained')::NUMERIC
                    / NULLIF(COUNT(DISTINCT sr.id), 0), 4
                ) AS complained_rate
            FROM sends s
            JOIN send_recipients sr ON sr.send_id = s.id
            JOIN send_attempts sa ON sa.send_id = s.id
            JOIN ip_addresses ia ON ia.id = sa.ip_address_id
            WHERE sa.created_at::DATE = :stat_date
            GROUP BY ia.id, s.project_id
            ON CONFLICT (ip_address_id, project_id, stat_date) DO UPDATE SET
                sent = EXCLUDED.sent,
                bounced_recipient = EXCLUDED.bounced_recipient,
                bounced_infrastructure = EXCLUDED.bounced_infrastructure,
                bounced_unknown = EXCLUDED.bounced_unknown,
                complained = EXCLUDED.complained,
                bounced_recipient_rate = EXCLUDED.bounced_recipient_rate,
                bounced_infrastructure_rate = EXCLUDED.bounced_infrastructure_rate,
                bounced_unknown_rate = EXCLUDED.bounced_unknown_rate,
                complained_rate = EXCLUDED.complained_rate
        SQL, ['stat_date' => $statDate]);
    }
}
