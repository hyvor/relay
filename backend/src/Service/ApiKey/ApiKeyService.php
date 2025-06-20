<?php

namespace App\Service\ApiKey;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Entity\Type\ApiKeyScope;
use App\Service\ApiKey\Dto\UpdateApiKeyDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ApiKeyService
{

    use ClockAwareTrait;

    const int MAX_API_KEY_PER_PROJECT = 5;

    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    /*
     * @return array{ apiKey: ApiKey, rawKey: string }
     */
    public function createApiKey(Project $project, string $name, ApiKeyScope $scope): array
    {
        $key = bin2hex(random_bytes(16));
        $apiKey = new ApiKey();
        $apiKey->setProject($project)
            ->setName($name)
            ->setScope($scope)
            ->setKey(hash('sha256', $key)) // Store the hashed key
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

        $apiKey->setUpdatedAt($this->now());

        $this->em->persist($apiKey);
        $this->em->flush();
        return $apiKey;
    }

    public function getApiKeysForProject(Project $project): array
    {
        return $this->em->getRepository(ApiKey::class)->findBy(['project' => $project]);
    }

    public function deleteApiKey(ApiKey $apiKey): void
    {
        $this->em->remove($apiKey);
        $this->em->flush();
    }
}