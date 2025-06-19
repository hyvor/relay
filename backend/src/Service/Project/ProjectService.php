<?php

namespace App\Service\Project;

use App\Entity\Project;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Arr;

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

    /**
     * @return ArrayCollection<int, Project>
     */
    public function getUsersProject(int $userId): ArrayCollection
    {
        $projects = $this->em->getRepository(Project::class)->findBy(['hyvor_user_id' => $userId]);
        return new ArrayCollection($projects);
    }
}