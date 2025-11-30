<?php

namespace App\Tests\Api\Console\Webhook;


use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Object\WebhookObject;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookObject::class)]
class GetWebhooksTest extends WebTestCase
{
    public function test_get_webhooks(): void
    {
        $project = ProjectFactory::createOne();

        $otherProject = ProjectFactory::createOne();

        $webhooks = WebhookFactory::createMany(10, [
            'project' => $project,
        ]);

        $otherProjectWebhooks = WebhookFactory::createMany(5, [
            'project' => $otherProject,
        ]);

        $response = $this->consoleApi(
            $project,
            'GET',
            '/webhooks'
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertCount(10, $content);
        foreach ($content as $key => $webhookData) {
            $this->assertIsArray($webhookData);
            $this->assertArrayHasKey('id', $webhookData);
            $this->assertArrayHasKey('url', $webhookData);
            $this->assertArrayHasKey('description', $webhookData);
            $this->assertIsString($webhookData['url']);
            $this->assertIsString($webhookData['description']);
        }
    }
}
