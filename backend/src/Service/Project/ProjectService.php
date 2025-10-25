<?php

namespace App\Service\Project;

use App\Api\Console\Authorization\Scope;
use App\Entity\Project;
use App\Entity\ProjectUser;
use App\Entity\Type\ProjectSendType;
use App\Service\Project\Dto\UpdateProjectDto;
use App\Service\Project\Event\ProjectCreatingEvent;
use App\Service\ProjectUser\ProjectUserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProjectService
{

    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
        private ProjectUserService $projectUserService,
    ) {
    }

    public function getProjectById(int $id): ?Project
    {
        return $this->em->getRepository(Project::class)->find($id);
    }

    /**
     * @return array{
     *     project: Project,
     *     projectUser: ($createProjectUser is true ? ProjectUser : null)
     * }
     */
    public function createProject(
        int $userId,
        string $name,
        ProjectSendType $sendType,
        bool $createProjectUser = true,
        bool $flush = true
    ): array {
        $this->ed->dispatch(new ProjectCreatingEvent($userId));

        $project = new Project();
        $project
            ->setUserId($userId)
            ->setName($name)
            ->setCreatedAt($this->now())
            ->setUpdatedAt($this->now())
            ->setSendType($sendType);

        $this->em->persist($project);

        if ($createProjectUser) {
            $projectUser = $this->projectUserService->createProjectUser(
                $project,
                $userId,
                Scope::all(),
                flush: false
            );
        }

        if ($flush) {
            $this->em->flush();
        }

        return [
            'project' => $project,
            'projectUser' => $createProjectUser ? $projectUser : null,
        ];
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
