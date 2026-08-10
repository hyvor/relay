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
        $statDate = $message->forLastDay
            ? (new \DateTimeImmutable('yesterday'))->format('Y-m-d')
            : (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->em->getConnection()->executeStatement(<<<SQL
            INSERT INTO stats_delivery_domain (
                project_id, ip_address_id, recipient_domain, provider, stat_date,
                sent, accepted, bounced_recipient, bounced_infrastructure, bounced_unknown, complained
            )
            SELECT
                s.project_id,
                ia.id,
                sa.domain AS recipient_domain,
                NULL AS provider,
                :stat_date AS stat_date,
                COUNT(DISTINCT sr.id) AS sent,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'accepted') AS accepted,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'recipient') AS bounced_recipient,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'infrastructure') AS bounced_infrastructure,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'bounced' AND sr.bounce_reason = 'unknown') AS bounced_unknown,
                COUNT(DISTINCT sr.id) FILTER (WHERE sr.status = 'complained') AS complained
            FROM sends s
            JOIN send_recipients sr ON sr.send_id = s.id
            JOIN send_attempts sa ON sa.send_id = s.id
            JOIN ip_addresses ia ON ia.id = sa.ip_address_id
            WHERE sa.created_at::DATE = :stat_date
            GROUP BY s.project_id, ia.id, sa.domain
            ON CONFLICT (project_id, ip_address_id, recipient_domain, stat_date) DO UPDATE SET
                sent = EXCLUDED.sent,
                accepted = EXCLUDED.accepted,
                bounced_recipient = EXCLUDED.bounced_recipient,
                bounced_infrastructure = EXCLUDED.bounced_infrastructure,
                bounced_unknown = EXCLUDED.bounced_unknown,
                complained = EXCLUDED.complained
        SQL, ['stat_date' => $statDate]);
    }
}
