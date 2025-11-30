<?php

namespace App\Tests\Service\Webhook\MessageHandler;

use App\Entity\WebhookDelivery;
use App\Service\Webhook\Message\ClearOldWebhookDeliveriesMessage;
use App\Service\Webhook\MessageHandler\ClearOldWebhookDeliveriesMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\WebhookDeliveryFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClearOldWebhookDeliveriesMessageHandler::class)]
class ClearOldWebhookDeliveriesMessageHandlerTest extends KernelTestCase
{

    public function test_deletes_deliveries_older_than_14_days(): void
    {
        $webhook = WebhookFactory::createOne();

        $delivery1 = WebhookDeliveryFactory::createOne([
            'webhook' => $webhook,
            'created_at' => new \DateTimeImmutable('-2 months'),
        ]);
        $delivery2 = WebhookDeliveryFactory::createOne([
            'webhook' => $webhook,
            'created_at' => new \DateTimeImmutable('-30 days'),
        ]);
        $delivery3 = WebhookDeliveryFactory::createOne([
            'webhook' => $webhook,
            'created_at' => new \DateTimeImmutable('-15 days'),
        ]);
        $delivery4 = WebhookDeliveryFactory::createOne([
            'webhook' => $webhook,
            'created_at' => new \DateTimeImmutable('-13 days'),
        ]);
        $delivery5 = WebhookDeliveryFactory::createOne([
            'webhook' => $webhook,
            'created_at' => new \DateTimeImmutable('-1 day'),
        ]);

        $transport = $this->transport('scheduler_default');
        $transport->send(new ClearOldWebhookDeliveriesMessage());
        $transport->throwExceptions()->process();

        $deliveries = $this->em->getRepository(WebhookDelivery::class)->findAll();
        $this->assertCount(2, $deliveries);

        $deliveryIds = array_map(fn(WebhookDelivery $delivery) => $delivery->getId(), $deliveries);
        $this->assertContains($delivery4->getId(), $deliveryIds);
        $this->assertContains($delivery5->getId(), $deliveryIds);
    }

}

