<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Stats\Message\UpdateStatsDeliveryDomainMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatsDeliveryDomainMessageHandler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(UpdateStatsDeliveryDomainMessage $message): void
    {
        $statDate = $message->forLastDay ? 'CURRENT_DATE - 1' : 'CURRENT_DATE';

        $this->em->getConnection()->executeStatement(<<<SQL
            INSERT INTO stats_delivery_domain (
                project_id, ip_address_id, recipient_domain, provider, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, complained
            )
            SELECT
                s.project_id,
                sa.ip_address_id,
                sa.domain AS recipient_domain,
                NULL AS provider,
                $statDate AS stat_date,
                COUNT(DISTINCT sr.id) AS sent,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'accepted') AS accepted,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'complained') AS complained
            FROM sends s
            JOIN send_recipients sr ON sr.send_id = s.id
            JOIN send_attempts sa ON sa.send_id = s.id
            WHERE sa.created_at::DATE = $statDate
            GROUP BY s.project_id, sa.ip_address_id, sa.domain, $statDate
            ON CONFLICT (project_id, ip_address_id, recipient_domain, stat_date) DO UPDATE SET
                sent = EXCLUDED.sent,
                accepted = EXCLUDED.accepted,
                bounced_recipient = EXCLUDED.bounced_recipient,
                bounced_infrastructure = EXCLUDED.bounced_infrastructure,
                complained = EXCLUDED.complained
        SQL);
    }
}
