<?php

namespace App\Service\Webhook\MessageHandler;

use App\Entity\WebhookDelivery;
use App\Service\Webhook\Message\ClearOldWebhookDeliveriesMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearOldWebhookDeliveriesMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function __invoke(ClearOldWebhookDeliveriesMessage $message): void
    {
        $this->em->createQueryBuilder()
            ->delete(WebhookDelivery::class, 'w')
            ->where('w.created_at < :date')
            ->setParameter('date', new \DateTimeImmutable('-14 days'))
            ->getQuery()
            ->execute();

        $this->em->flush();
    }
}
