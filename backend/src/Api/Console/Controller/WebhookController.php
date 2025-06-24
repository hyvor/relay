<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\CreateWebhookInput;
use App\Api\Console\Object\WebhookObject;
use App\Entity\Project;
use App\Service\Webhook\WebhookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(
        private WebhookService $webhookService
    ) {}

    #[Route('/webhooks', methods: 'POST')]
    public function createWebhook(#[MapRequestPayload] CreateWebhookInput $input, Project $project): JsonResponse
    {
        $webhook = $this->webhookService->createWebhook(
            $project,
            $input->url,
            $input->description
        );

        return $this->json(new WebhookObject($webhook));
    }
}