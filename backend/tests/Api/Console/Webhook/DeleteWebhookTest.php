<?php

namespace App\Tests\Api\Console\Webhook;

use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Webhook;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookObject::class)]
class DeleteWebhookTest extends WebTestCase
{
    public function test_delete_webhook(): void
    {
        $project = ProjectFactory::createOne();

        $webhook = WebhookFactory::createOne(
            [
                'project' => $project,
            ]
        );

        $webhookId = $webhook->getId();

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/webhooks/' . $webhookId
        );

        $this->assertSame(200, $response->getStatusCode());
        $content = $this->getJson();
        $this->assertEmpty($content);

        $webhookDb = $this->em->getRepository(Webhook::class)->find($webhookId);
        $this->assertNull($webhookDb);
    }

    public function test_delete_non_existent_webhook(): void
    {
        $project = ProjectFactory::createOne();

        $response = $this->consoleApi(
            $project,
            'DELETE',
            '/webhooks/999999' // Assuming this ID does not exist
        );

        $this->assertSame(404, $response->getStatusCode());
    }
}