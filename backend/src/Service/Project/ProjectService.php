<?php

namespace App\Service\Project;

use App\Api\Console\Authorization\Scope;
use App\Entity\Project;
use App\Entity\ProjectUser;
use App\Entity\Type\ProjectSendType;
use App\Repository\InstanceRepository;
use App\Service\Project\Dto\UpdateProjectDto;
use App\Service\Project\Event\ProjectCreatingEvent;
use App\Service\Project\Event\ProjectsDeletedEvent;
use App\Service\ProjectUser\ProjectUserService;
use Doctrine\ORM\EntityManagerInterface;
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Resource\ResourceCreated;
use Hyvor\Internal\Component\Component;
use Hyvor\Internal\Deployment;
use Hyvor\Internal\InternalConfig;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProjectService
{

    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private EventDispatcherInterface $ed,
		private ProjectUserService $projectUserService,
		private CommsInterface $comms,
        private InternalConfig $internalConfig,
        private InstanceRepository $instanceRepository,
    ) {
    }

    public function getTotalProjectsCount(): int
    {
        return (int)$this->em->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.deleted_at IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getProjectById(int $id): ?Project
    {
        $project = $this->em->getRepository(Project::class)->find($id);
        if ($project === null || $project->getDeletedAt() !== null) {
            return null;
        }
        return $project;
    }

    public function getProjectByIdIncludingDeleted(int $id): ?Project
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
		int $organizationId,
        string $name,
        ProjectSendType $sendType,
        bool $createProjectUser = true,
        bool $isSystemProject = false,
        bool $flush = true
    ): array {
        $this->ed->dispatch(new ProjectCreatingEvent($userId));

        $project = new Project();
		$project
			->setOrganizationId($organizationId)
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

		if ($this->internalConfig->getDeployment() === Deployment::CLOUD && !$isSystemProject) {
			$this->comms->send(new ResourceCreated(
				Component::RELAY,
				$organizationId
			));
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

    /**
     * Soft-delete one or more projects in a single transaction.
     * Throws if any project in the batch is the system project.
     * Idempotent: already-deleted projects are skipped.
     *
     * @param Project[] $projects
     */
    public function deleteProjects(array $projects): void
    {
        if ($projects === []) {
            return;
        }

        $instance = $this->instanceRepository->findFirst();
        $systemProjectId = $instance?->getSystemProject()?->getId();

        $deleted = [];
        foreach ($projects as $project) {
            if ($systemProjectId !== null && $project->getId() === $systemProjectId) {
                throw new \LogicException('Cannot delete the system project.');
            }
            if ($project->getDeletedAt() !== null) {
                continue;
            }
            $project->setDeletedAt($this->now());
            $deleted[] = $project;
        }

        if ($deleted === []) {
            return;
        }

        $this->em->flush();

        // TODO(email): notify each affected owner with a single email listing all deleted projects;
        // mention the 30-day grace period before hard delete. Out of scope for this PR.

        $this->ed->dispatch(new ProjectsDeletedEvent($deleted));
    }

    public function deleteProject(Project $project): void
    {
        $this->deleteProjects([$project]);
    }

    public function undeleteProject(Project $project): void
    {
        $project->setDeletedAt(null);
        $this->em->flush();
    }

    /**
     * Hard-deletes projects soft-deleted at or before $cutoff.
     * FK cascades at the DB level remove all related rows.
     */
    public function hardDeleteSoftDeletedBefore(\DateTimeImmutable $cutoff): void
    {
        /** @var Project[] $projects */
        $projects = $this->em->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->andWhere('p.deleted_at IS NOT NULL')
            ->andWhere('p.deleted_at <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        if ($projects === []) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($projects) {
            foreach ($projects as $project) {
                $this->em->remove($project);
            }

            $this->em->flush();
        });
    }
}
