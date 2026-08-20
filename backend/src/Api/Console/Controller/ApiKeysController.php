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
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ApiKeysController extends AbstractController
{
    public function __construct(
        private ApiKeyService $apiKeyService
    ) {
    }

    #[Route('/api-keys', methods: 'POST')]
    #[ScopeRequired(Scope::API_KEYS_WRITE)]
    #[OA\Post(
        summary: 'Create a new API key',
        description: 'Creates a new API key for the project. The raw key is returned only once and must be stored securely.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the created API key. The `key` property is the raw key, which is only returned once.',
        content: new Model(type: ApiKeyObject::class)
    )]
    public function create(#[MapRequestPayload] CreateApiKeyInput $input, Project $project): JsonResponse
    {
        $apiKeysCount = count($this->apiKeyService->getApiKeysForProject($project));
        if ($apiKeysCount >= ApiKeyService::MAX_API_KEY_PER_PROJECT) {
            throw new BadRequestHttpException("You have reached the maximum number of API keys for this project.");
        }

        $creation = $this->apiKeyService->createApiKey(
            $project,
            $input->name,
            $input->scopes,
            $input->allowed_ips,
        );

        return $this->json(new ApiKeyObject($creation['apiKey'], $creation['rawKey']));
    }

    #[Route('/api-keys', methods: 'GET')]
    #[ScopeRequired(Scope::API_KEYS_READ)]
    #[OA\Get(
        summary: 'Get all API keys',
        description: 'Returns all API keys of the project. The raw key is not returned; only its metadata is.'
    )]
    #[OA\Response(
        response: 200,
        description: 'List of API keys',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: ApiKeyObject::class))
        )
    )]
    public function list(Project $project): JsonResponse
    {
        $apiKeys = $this->apiKeyService->getApiKeysForProject($project);
        $apiKeyObjects = array_map(fn(ApiKey $apiKey) => new ApiKeyObject($apiKey), $apiKeys);

        return $this->json($apiKeyObjects);
    }

    #[Route('/api-keys/{id}', methods: 'PATCH')]
    #[ScopeRequired(Scope::API_KEYS_WRITE)]
    #[OA\Patch(
        summary: 'Update an API key',
        description: 'Updates the name, scopes, enabled status, or allowed IPs of an API key.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns the updated API key object.',
        content: new Model(type: ApiKeyObject::class)
    )]
    public function update(#[MapRequestPayload] UpdateApiKeyInput $input, ApiKey $apiKey): JsonResponse
    {
        $updates = new UpdateApiKeyDto();
        if ($input->hasProperty('is_enabled')) {
            $updates->enabled = $input->is_enabled;
        }
        if ($input->hasProperty('name')) {
            $updates->name = $input->name;
        }

        $recheckAllowedIps = false;
        if ($input->hasProperty('scopes')) {
            $updates->scopes = $input->scopes;
            $recheckAllowedIps = true;
        }
        if ($input->hasProperty('allowed_ips')) {
            $updates->allowedIps = $input->allowed_ips;
            $recheckAllowedIps = true;
        }

        if ($recheckAllowedIps) {
            $scopes = $input->scopes ?? $apiKey->getScopes();
            $allowedIps = $input->allowed_ips ?? $apiKey->getAllowedIps();

            if (in_array(Scope::SENDS_SEND->value, $scopes, true) && count($allowedIps) === 0) {
                throw new BadRequestHttpException('At least one allowed IP is required when the "sends.send" scope is enabled.');
            }
        }

        $updatedApiKey = $this->apiKeyService->updateApiKey($apiKey, $updates);

        return $this->json(new ApiKeyObject($updatedApiKey));
    }

    #[Route('/api-keys/{id}', methods: 'DELETE')]
    #[ScopeRequired(Scope::API_KEYS_WRITE)]
    #[OA\Delete(
        summary: 'Delete an API key',
        description: 'Permanently deletes an API key. Requests made with the deleted key will be rejected immediately.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns an empty object on success.',
        content: new OA\JsonContent()
    )]
    public function delete(ApiKey $apiKey): JsonResponse
    {
        $this->apiKeyService->deleteApiKey($apiKey);

        return $this->json([]);
    }
}
