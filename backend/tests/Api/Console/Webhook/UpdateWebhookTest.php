<?php

namespace App\Tests\Api\Console\Webhook;

use App\Api\Console\Controller\WebhookController;
use App\Api\Console\Input\UpdateWebhookInput;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use App\Service\Webhook\WebhookService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\WebhookFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookService::class)]
#[CoversClass(WebhookObject::class)]
#[CoversClass(Webhook::class)]
#[CoversClass(WebhookRepository::class)]
#[CoversClass(UpdateWebhookInput::class)]
class UpdateWebhookTest extends WebTestCase
{
    public function test_update_webhook(): void
    {
        $project = ProjectFactory::createOne();

        $webhook = WebhookFactory::createOne(
            [
                'project' => $project,
                'url' => 'https://example.com/old',
                'description' => 'Old description',
            ]
        );

        $response = $this->consoleApi(
            $project,
            'PATCH',
            '/webhooks/' . $webhook->getId(),
            [
                'url' => 'https://example.com/new',
                'description' => 'New description',
                'events' => ['send.complained', 'suppression.created'],
            ]
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = $this->getJson();
        $this->assertArrayHasKey('id', $content);
        $this->assertArrayHasKey('url', $content);
        $this->assertArrayHasKey('description', $content);
        $this->assertSame('https://example.com/new', $content['url']);
        $this->assertSame('New description', $content['description']);
        $this->assertContains('send.complained', (array) $content['events']);
        $this->assertContains('suppression.created', (array) $content['events']);

        $webhookDb = $this->em->getRepository(Webhook::class)->find($webhook->getId());
        $this->assertNotNull($webhookDb);
        $this->assertSame('https://example.com/new', $webhookDb->getUrl());
        $this->assertSame('New description', $webhookDb->getDescription());
        $this->assertContains('send.complained', $webhookDb->getEvents());
        $this->assertContains('suppression.created', $webhookDb->getEvents());
    }
}