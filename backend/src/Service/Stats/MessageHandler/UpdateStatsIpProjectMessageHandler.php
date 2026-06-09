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
        $this->em->getConnection()->executeStatement(<<<SQL
            INSERT INTO stats_ip_project (
                ip_address, project_id, stat_date,
                sent, bounced_recipient, bounced_infrastructure, complained,
                bounced_recipient_rate, bounced_infrastructure_rate, complained_rate
            )
            SELECT
                sa.ip_address,
                s.project_id,
                CURRENT_DATE AS stat_date,
                COUNT(*) FILTER (WHERE sr.status IN ('accepted', 'deferred', 'bounced', 'failed', 'suppressed')) AS sent,
                COUNT(*) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                COUNT(*) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                COUNT(*) FILTER (WHERE sr.status = 'complained') AS complained,
                ROUND(
                    COUNT(*) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient')::NUMERIC
                    / NULLIF(COUNT(*), 0), 4
                ) AS bounced_recipient_rate,
                ROUND(
                    COUNT(*) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure')::NUMERIC
                    / NULLIF(COUNT(*), 0), 4
                ) AS bounced_infrastructure_rate,
                ROUND(
                    COUNT(*) FILTER (WHERE sr.status = 'complained')::NUMERIC
                    / NULLIF(COUNT(*), 0), 4
                ) AS complained_rate
            FROM sends s
            JOIN send_recipients sr ON sr.send_id = s.id
            JOIN send_attempts sa ON sa.send_id = s.id
            WHERE sa.created_at::DATE = CURRENT_DATE
            GROUP BY sa.ip_address, s.project_id, CURRENT_DATE
            ON CONFLICT (ip_address, project_id, stat_date) DO UPDATE SET
                sent = EXCLUDED.sent,
                bounced_recipient = EXCLUDED.bounced_recipient,
                bounced_infrastructure = EXCLUDED.bounced_infrastructure,
                complained = EXCLUDED.complained,
                bounced_recipient_rate = EXCLUDED.bounced_recipient_rate,
                bounced_infrastructure_rate = EXCLUDED.bounced_infrastructure_rate,
                complained_rate = EXCLUDED.complained_rate
        SQL);
    }
}
