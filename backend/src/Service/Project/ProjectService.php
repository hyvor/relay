<?php

namespace App\Service\Project;

use App\Entity\Project;
use App\Service\Project\Dto\UpdateProjectDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Arr;

class ProjectService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function getProjectById(int $id): ?Project
    {
        return $this->em->getRepository(Project::class)->find($id);
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

    /**
     * @return ArrayCollection<int, Project>
     */
    public function getUsersProject(int $userId): ArrayCollection
    {
        $projects = $this->em->getRepository(Project::class)->findBy(['hyvor_user_id' => $userId]);
        return new ArrayCollection($projects);
    }

    public function updateProject(Project $project, UpdateProjectDto $updates): Project
    {
        if ($updates->hasProperty('name')) {
            $project->setName($updates->name);
        }

        $project->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $project;
    }
}