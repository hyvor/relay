<?php

namespace App\Service\Webhook;

use App\Entity\Project;
use App\Entity\WebhookDelivery;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class WebhookDeliveryService
{
    public function __construct(
        private WebhookService $webhookService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return ArrayCollection<int, WebhookDelivery>
     */
    public function getWebhookDeliveriesForProject(Project $project, ?int $webhookId): ArrayCollection
    {
        $webhooks = $this->webhookService->getWebhooksForProject($project);

        if ($webhookId !== null) {
            $webhooks = $webhooks->filter(fn($webhook) => $webhook->getId() === $webhookId);
        }

        /** @var WebhookDelivery[] $deliveries */
        $deliveries = $this->em->getRepository(WebhookDelivery::class)
            ->createQueryBuilder('wd')
            ->where('wd.webhook IN (:webhooks)')
            ->setParameter('webhooks', $webhooks)
            ->orderBy('wd.id', 'DESC')
            ->getQuery()
            ->getResult();

        return new ArrayCollection($deliveries);
    }

    public function getLast24HoursDeliveriesCount(): int
    {
        $since = new \DateTimeImmutable('-24 hours');

        $count = $this->em->getRepository(WebhookDelivery::class)
            ->createQueryBuilder('wd')
            ->select('COUNT(wd.id)')
            ->where('wd.created_at >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$count;
    }
}