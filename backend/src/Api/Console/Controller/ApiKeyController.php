<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Input\CreateApiKeyInput;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\ApiKeyService;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
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
        $creation = $this->apiKeyService->createApiKey($project, $input->name, $input->scope);

        return $this->json(new ApiKeyObject($creation['apiKey'], $creation['rawKey']));
    }

    #[Route('/api-keys/{id}', methods: 'PATCH')]
    public function updateApiKey(#[MapRequestPayload] UpdateApiKeyDto $input, ApiKey $apiKey): JsonResponse
    {
        $updates = new UpdateApiKeyDto();
        $updates->enabled = $input->enabled;

        $updatedApiKey = $this->apiKeyService->updateApiKey($apiKey, $updates);

        return $this->json(new ApiKeyObject($updatedApiKey));
    }
}