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
use Hyvor\Internal\Util\Crypt\Encryption;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class WebhooksController extends AbstractController
{
    public function __construct(
        private WebhookService $webhookService,
        private WebhookDeliveryService $webhookDeliveryService,
        private Encryption $encryption,
    ) {
    }

    #[Route('/webhooks', methods: 'GET')]
    #[ScopeRequired(Scope::WEBHOOKS_READ)]
    #[OA\Get(
        summary: 'Get all webhooks',
        description: 'Returns all webhooks configured for the project, including their secrets.'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of webhooks',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: WebhookObject::class))
        )
    )]
    public function list(Project $project): JsonResponse
    {
        $webhooks = $this->webhookService->getWebhooksForProject($project)
            ->map(fn($webhook) => new WebhookObject(
                $webhook,
                $this->encryption->decryptString($webhook->getSecretEncrypted())
            ));

        return $this->json($webhooks);
    }

    #[Route('/webhooks', methods: 'POST')]
    #[ScopeRequired(Scope::WEBHOOKS_WRITE)]
    #[OA\Post(
        summary: 'Create a webhook',
        description: 'Creates a new webhook for the project and returns its signing secret.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the created webhook object with its secret.',
        content: new Model(type: WebhookObject::class)
    )]
    public function create(#[MapRequestPayload] CreateWebhookInput $input, Project $project): JsonResponse
    {
        $creation = $this->webhookService->createWebhook(
            $project,
            $input->url,
            $input->description,
            $input->events
        );

        return $this->json(new WebhookObject($creation['webhook'], $creation['secret']));
    }

    #[Route('/webhooks/{id}', methods: 'PATCH')]
    #[ScopeRequired(Scope::WEBHOOKS_WRITE)]
    #[OA\Patch(
        summary: 'Update a webhook',
        description: 'Updates the URL, description, or events of a webhook.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the updated webhook object. The secret is not returned.',
        content: new Model(type: WebhookObject::class)
    )]
    public function update(#[MapRequestPayload] UpdateWebhookInput $input, Webhook $webhook): JsonResponse
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
    #[OA\Delete(
        summary: 'Delete a webhook',
        description: 'Permanently deletes a webhook.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns an empty object on success.',
        content: new OA\JsonContent()
    )]
    public function delete(Webhook $webhook): JsonResponse
    {
        $this->webhookService->deleteWebhook($webhook);

        return new JsonResponse([]);
    }

    #[Route('/webhooks/deliveries', methods: 'GET')]
    #[ScopeRequired(Scope::WEBHOOKS_READ)]
    #[OA\Get(
        summary: 'Get webhook deliveries',
        description: 'Returns delivery attempts for webhooks of the project.'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of webhook deliveries',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: WebhookDeliveryObject::class))
        )
    )]
    public function listDeliveries(Request $request, Project $project): JsonResponse
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
