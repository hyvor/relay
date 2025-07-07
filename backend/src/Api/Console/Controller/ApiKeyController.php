<?php

namespace App\Api\Console\Controller;

use App\Api\Console\Authorization\Scope;
use App\Api\Console\Authorization\ScopeRequired;
use App\Api\Console\Input\CreateApiKeyInput;
use App\Api\Console\Input\UpdateApiKeyInput;
use App\Api\Console\Object\ApiKeyObject;
use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\ApiKeyService;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ApiKeyController extends AbstractController
{
    public function __construct(
        private ApiKeyService $apiKeyService
    )
    {
    }

    #[Route('/api-keys', methods: 'POST')]
    #[ScopeRequired(Scope::API_KEYS_WRITE)]
    public function createApiKey(#[MapRequestPayload] CreateApiKeyInput $input, Project $project): JsonResponse
    {
        $apiKeysCount = count($this->apiKeyService->getApiKeysForProject($project));
        if ($apiKeysCount >= ApiKeyService::MAX_API_KEY_PER_PROJECT) {
            throw new BadRequestHttpException("You have reached the maximum number of API keys for this project.");
        }

        $creation = $this->apiKeyService->createApiKey($project, $input->name, $input->scopes);

        return $this->json(new ApiKeyObject($creation['apiKey'], $creation['rawKey']));
    }

    #[Route('/api-keys', methods: 'GET')]
    #[ScopeRequired(Scope::API_KEYS_READ)]
    public function getApiKeys(Project $project): JsonResponse
    {
        $apiKeys = $this->apiKeyService->getApiKeysForProject($project);
        $apiKeyObjects = array_map(fn(ApiKey $apiKey) => new ApiKeyObject($apiKey), $apiKeys);

        return $this->json($apiKeyObjects);
    }

    #[Route('/api-keys/{id}', methods: 'PATCH')]
    #[ScopeRequired(Scope::API_KEYS_WRITE)]
    public function updateApiKey(#[MapRequestPayload] UpdateApiKeyInput $input, ApiKey $apiKey): JsonResponse
    {
        $updates = new UpdateApiKeyDto();
        if ($input->hasProperty('enabled'))
            $updates->enabled = $input->enabled;
        if ($input->hasProperty('scopes'))
            $updates->scopes = $input->scopes;
        if ($input->hasProperty('name'))
            $updates->name = $input->name;

        $updatedApiKey = $this->apiKeyService->updateApiKey($apiKey, $updates);

        return $this->json(new ApiKeyObject($updatedApiKey));
    }

    #[Route('/api-keys/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::API_KEYS_WRITE)]
    public function deleteApiKey(ApiKey $apiKey): JsonResponse
    {
        $this->apiKeyService->deleteApiKey($apiKey);

        return $this->json([]);
    }
}
