<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\CreateApiKeyInput;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\Project;
use App\Service\ApiKey\ApiKeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ApiKeyController extends AbstractController
{
    public function __construct(
        private ApiKeyService $apiKeyService
    )
    {
    }

    #[Route('/api-keys', methods: 'POST')]
    public function createApiKey(#[MapRequestPayload] CreateApiKeyInput $input, Project $project): JsonResponse
    {
        $apiKey = $this->apiKeyService->createApiKey($project, $input->name, $input->scope);

        return $this->json(new ApiKeyObject($apiKey));
    }
}