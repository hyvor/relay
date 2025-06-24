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
    )
    {
    }

    /**
     * @return ArrayCollection<int, WebhookDelivery>
     */
    public function getWebhookDeliveriesForProject(Project $project): ArrayCollection
    {
        $webhooks = $this->webhookService->getWebhooksForProject($project);

        $deliveries = $this->em->getRepository(WebhookDelivery::class)
            ->createQueryBuilder('wd')
            ->where('wd.webhook IN (:webhooks)')
            ->setParameter('webhooks', $webhooks)
            ->getQuery()
            ->getResult();

        return new ArrayCollection($deliveries);
    }
}