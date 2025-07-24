<?php

namespace App\Tests\Api\Console\Webhook;

use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Type\WebhookDeliveryStatus;
use App\Entity\Webhook;
use App\Entity\WebhookDelivery;
use App\Repository\WebhookDeliveryRepository;
use App\Service\Webhook\WebhookDeliveryService;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\WebhookDeliveryFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookDeliveryRepository::class)]
#[CoversClass(WebhookDeliveryService::class)]
#[CoversClass(WebhookObject::class)]
#[CoversClass(WebhookDelivery::class)]
class GetWebhookDeliveriesTest extends WebTestCase
{
    public function test_get_webhook_deliveries(): void
    {
        $project = ProjectFactory::createOne();

        $webhook = WebhookFactory::createOne(
            [
                'project' => $project,
                'url' => 'https://example.com/webhook',
                'description' => 'Test Webhook',
                'events' => ['send.delivered'],
            ]
        );

        $webhookDeliveries = WebhookDeliveryFactory::createMany(5,
        [
                'webhook' => $webhook,
                'status' => WebhookDeliveryStatus::PENDING,
            ]
        );

        $response = $this->consoleApi(
            $project,
            'GET',
            '/webhooks/deliveries'
        );
        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(5, $content);

        $i = 0;
        foreach ($content as $delivery) {
            $this->assertIsArray($delivery);
            $this->assertArrayHasKey('id', $delivery);
            $this->assertArrayHasKey('status', $delivery);
            $this->assertArrayHasKey('created_at', $delivery);
            $this->assertSame($webhookDeliveries[$i]->getId(), $delivery['id']);
            $this->assertSame(WebhookDeliveryStatus::PENDING->value, $delivery['status']);
            $i++;
        }
    }

    public function test_get_webhook_deliveries_specific_webhook(): void
    {
        $project = ProjectFactory::createOne();

        $webhook1 = WebhookFactory::createOne(
            [
                'project' => $project,
                'url' => 'https://example.com/webhook1',
                'description' => 'Test Webhook 1',
                'events' => ['send.delivered'],
            ]
        );

        $webhook2 = WebhookFactory::createOne(
            [
                'project' => $project,
                'url' => 'https://example.com/webhook2',
                'description' => 'Test Webhook 2',
                'events' => ['send.delivered'],
            ]
        );

        WebhookDeliveryFactory::createMany(3, [
            'webhook' => $webhook1,
            'status' => WebhookDeliveryStatus::PENDING,
        ]);

        WebhookDeliveryFactory::createMany(2, [
            'webhook' => $webhook2,
            'status' => WebhookDeliveryStatus::PENDING,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/webhooks/deliveries?webhookId=' . $webhook1->getId()
        );
        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(3, $content);
    }

    public function test_get_webhook_deliveries_empty(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'GET',
            '/webhooks/deliveries'
        );
        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(0, $content);
    }
}
