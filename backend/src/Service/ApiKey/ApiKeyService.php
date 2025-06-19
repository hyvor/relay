<?php

namespace App\Service\ApiKey;

use App\Entity\ApiKey;
use App\Entity\Project;
use App\Entity\Type\ApiKeyScope;
use Doctrine\ORM\EntityManagerInterface;

class ApiKeyService
{

    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    public function createApiKey(Project $project, string $name, ApiKeyScope $scope): ApiKey
    {
        $key = bin2hex(random_bytes(16));
        $apiKey = new ApiKey();
        $apiKey->setProject($project)
            ->setName($name)
            ->setScope($scope)
            ->setKey(hash('sha256', $key)) // Store the hashed key
            ->setEnabled(true)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($apiKey);
        $this->em->flush();
        return $apiKey;
    }
}