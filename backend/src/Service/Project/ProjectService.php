<?php

namespace App\Service\Project;

use App\Entity\Project;
use App\Entity\Type\ProjectSendType;
use App\Service\Project\Dto\UpdateProjectDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Arr;
use Symfony\Component\Clock\ClockAwareTrait;

class ProjectService
{

    use ClockAwareTrait;

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
        string $name,
        ProjectSendType $sendType,
    ): Project {
        $project = new Project();
        $project
            ->setUserId($userId)
            ->setName($name)
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setSendType($sendType);

        $this->em->persist($project);
        $this->em->flush();

        return $project;
    }

    /**
     * @return ArrayCollection<int, Project>
     */
    public function getUsersProject(int $userId): ArrayCollection
    {
        $projects = $this->em->getRepository(Project::class)->findBy(['user_id' => $userId]);
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