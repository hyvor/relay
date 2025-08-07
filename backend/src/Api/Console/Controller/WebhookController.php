<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\CreateWebhookInput;
use App\Api\Console\Input\UpdateWebhookInput;
use App\Api\Console\Object\WebhookDeliveryObject;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Project;
use App\Entity\Webhook;
use App\Service\Webhook\Dto\UpdateWebhookDto;
use App\Service\Webhook\WebhookDeliveryService;
use App\Service\Webhook\WebhookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private WebhookService $webhookService,
        private WebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    #[Route('/webhooks', methods: 'GET')]
    #[ScopeRequired(Scope::WEBHOOKS_READ)]
    public function getWebhooks(Project $project): JsonResponse
    {
        $webhooks = $this->webhookService->getWebhooksForProject($project)
            ->map(fn($webhook) => new WebhookObject($webhook));

        return $this->json($webhooks);
    }

    #[Route('/webhooks', methods: 'POST')]
    #[ScopeRequired(Scope::WEBHOOKS_WRITE)]
    public function createWebhook(#[MapRequestPayload] CreateWebhookInput $input, Project $project): JsonResponse
    {
        $webhook = $this->webhookService->createWebhook(
            $project,
            $input->url,
            $input->description,
            $input->events
        );

        return $this->json(new WebhookObject($webhook));
    }

    #[Route('/webhooks/{id}', methods: 'PATCH')]
    #[ScopeRequired(Scope::WEBHOOKS_WRITE)]
    public function updateWebhook(#[MapRequestPayload] UpdateWebhookInput $input, Webhook $webhook): JsonResponse
    {
        $updates = new UpdateWebhookDto();
        $updates->url = $input->url;
        $updates->description = $input->description;
        $updates->events = $input->events;

        $updatedWebhook = $this->webhookService->updateWebhook($webhook, $updates);

        return $this->json(new WebhookObject($updatedWebhook));
    }

    #[Route('/webhooks/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::WEBHOOKS_WRITE)]
    public function deleteWebhook(Webhook $webhook): JsonResponse
    {
        $this->webhookService->deleteWebhook($webhook);

        return new JsonResponse([]);
    }

    #[Route('/webhooks/deliveries', methods: 'GET')]
    #[ScopeRequired(Scope::WEBHOOKS_READ)]
    public function getWebhookDeliveries(Request $request, Project $project): JsonResponse
    {
        $webhookId = null;
        if ($request->query->has('webhook_id')) {
            $webhookId = $request->query->getInt('webhook_id');
        }

        $deliveries = $this->webhookDeliveryService->getWebhookDeliveriesForProject($project, $webhookId);
        $webhookDeliveryObjects = $deliveries->map(fn($delivery) => new WebhookDeliveryObject($delivery));
        return $this->json($webhookDeliveryObjects);
    }
}
