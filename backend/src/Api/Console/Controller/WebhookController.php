<?php

namespace App\Api\Console\Controller;

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
    ) {}

    #[Route('/webhooks', methods: 'POST')]
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

    #[Route('/webhooks', methods: 'GET')]
    public function getWebhooks(Project $project): JsonResponse
    {
        $webhooks = $this->webhookService->getWebhooksForProject($project)
            ->map(fn($webhook) => new WebhookObject($webhook));

        return $this->json($webhooks);
    }

    #[Route('/webhooks/{id}', methods: 'DELETE')]
    public function deleteWebhook(Webhook $webhook): JsonResponse
    {
        $this->webhookService->deleteWebhook($webhook);

        return new JsonResponse([]);
    }

    #[Route('/webhooks/{id}', methods: 'PATCH')]
    public function updateWebhook(#[MapRequestPayload] UpdateWebhookInput $input, Webhook $webhook): JsonResponse
    {
        $updates = new UpdateWebhookDto();
        $updates->url = $input->url;
        $updates->description = $input->description;
        $updates->events = $input->events;

        $updatedWebhook = $this->webhookService->updateWebhook($webhook, $updates);

        return $this->json(new WebhookObject($updatedWebhook));
    }

    #[Route('/webhooks/deliveries', methods: 'GET')]
    public function getWebhookDeliveries(Request $request, Project $project): JsonResponse
    {
        $webhookId = null;
        if ($request->query->has('webhookId')) {
            $webhookId = $request->query->getInt('webhookId');
        }

        $deliveries = $this->webhookDeliveryService->getWebhookDeliveriesForProject($project, $webhookId);
        $webhookDeliveryObjects = $deliveries->map(fn($delivery) => new WebhookDeliveryObject($delivery));
        return $this->json($webhookDeliveryObjects);
    }
}