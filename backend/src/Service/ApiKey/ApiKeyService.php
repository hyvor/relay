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
}