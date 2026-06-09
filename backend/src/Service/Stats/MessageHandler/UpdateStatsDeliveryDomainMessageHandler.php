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
        $this->em->getConnection()->executeStatement(<<<SQL
            INSERT INTO stats_delivery_domain (
                project_id, ip_address, recipient_domain, provider, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, complained
            )
            SELECT
                s.project_id,
                sa.ip_address,
                sa.domain AS recipient_domain,
                NULL AS provider,
                CURRENT_DATE AS stat_date,
                COUNT(*) AS sent,
                COUNT(*) FILTER (WHERE sr.status = 'accepted') AS accepted,
                COUNT(*) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                COUNT(*) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                COUNT(*) FILTER (WHERE sr.status = 'complained') AS complained
            FROM sends s
            JOIN send_recipients sr ON sr.send_id = s.id
            JOIN send_attempts sa ON sa.send_id = s.id
            WHERE sa.created_at::DATE = CURRENT_DATE
            GROUP BY s.project_id, sa.ip_address, sa.domain, CURRENT_DATE
            ON CONFLICT (project_id, ip_address, recipient_domain, stat_date) DO UPDATE SET
                sent = EXCLUDED.sent,
                accepted = EXCLUDED.accepted,
                bounced_recipient = EXCLUDED.bounced_recipient,
                bounced_infrastructure = EXCLUDED.bounced_infrastructure,
                complained = EXCLUDED.complained
        SQL);
    }
}
