<?php

namespace App\Service\Project;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

class ProjectService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function createProject(
        int $userId,
        string $name
    ): Project {
        $project = new Project();
        $project->setHyvorUserId($userId)
            ->setName($name)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($project);
        $this->em->flush();

        return $project;
    }
}