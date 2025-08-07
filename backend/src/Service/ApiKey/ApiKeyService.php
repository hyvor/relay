<?php

namespace App\Service\ApiKey;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ApiKeyService
{

    use ClockAwareTrait;

    const int MAX_API_KEY_PER_PROJECT = 10;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param string[] $scopes
     * @return array{ apiKey: ApiKey, rawKey: string }
     */
    public function createApiKey(Project $project, string $name, array $scopes): array
    {
        $key = bin2hex(random_bytes(16));
        $apiKey = new ApiKey();
        $apiKey->setProject($project)
            ->setName($name)
            ->setScopes($scopes)
            ->setKeyHashed(hash('sha256', $key)) // Store the hashed key
            ->setIsEnabled(true)
            ->setCreatedAt($this->now())
            ->setLastAccessedAt(null)
            ->setUpdatedAt($this->now());

        $this->em->persist($apiKey);
        $this->em->flush();
        return [
            'apiKey' => $apiKey,
            'rawKey' => $key,
        ];
    }

    public function updateApiKey(ApiKey $apiKey, UpdateApiKeyDto $updates): ApiKey
    {
        if ($updates->hasProperty('enabled')) {
            $apiKey->setIsEnabled($updates->enabled);
        }

        if ($updates->hasProperty('scopes')) {
            $apiKey->setScopes($updates->scopes);
        }

        if ($updates->hasProperty('name')) {
            $apiKey->setName($updates->name);
        }

        if ($updates->hasProperty('lastAccessedAt')) {
            $apiKey->setLastAccessedAt($updates->lastAccessedAt);
        }

        $apiKey->setUpdatedAt($this->now());

        $this->em->persist($apiKey);
        $this->em->flush();
        return $apiKey;
    }

    /**
     * @return ApiKey[]
     */
    public function getApiKeysForProject(Project $project): array
    {
        return $this->em->getRepository(ApiKey::class)->findBy(['project' => $project]);
    }

    public function getByRawKey(string $rawKey): ?ApiKey
    {
        $hashedKey = hash('sha256', $rawKey);
        return $this->em->getRepository(ApiKey::class)->findOneBy(['key_hashed' => $hashedKey]);
    }

    public function deleteApiKey(ApiKey $apiKey): void
    {
        $this->em->remove($apiKey);
        $this->em->flush();
    }
}
