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
use Hyvor\Internal\Bundle\Comms\CommsInterface;
use Hyvor\Internal\Bundle\Comms\Event\ToCore\Resource\ResourceCreated;
use Hyvor\Internal\Component\Component;
use Hyvor\Internal\Deployment;
use Hyvor\Internal\InternalConfig;
use Psr\Log\LoggerInterface;
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
        private InternalConfig $internalConfig
    ) {
    }

    public function getTotalProjectsCount(): int
    {
        return $this->em->getRepository(Project::class)->count();
    }

    public function getProjectById(int $id): ?Project
    {
        return $this->em->getRepository(Project::class)->find($id);
    }

    /**
     * Distinct, non-null organization ids referenced by projects, ordered by
     * id descending. Paginated via a cursor ($beforeId) since there could be
     * thousands of distinct organizations.
     *
     * @return int[]
     */
    public function getDistinctOrganizationIds(int $limit, ?int $beforeId = null): array
    {
        $qb = $this->em->getRepository(Project::class)->createQueryBuilder('p')
            ->select('DISTINCT p.organization_id AS organization_id')
            ->where('p.organization_id IS NOT NULL')
            ->orderBy('p.organization_id', 'DESC')
            ->setMaxResults($limit);

        if ($beforeId !== null) {
            $qb->andWhere('p.organization_id < :beforeId')
                ->setParameter('beforeId', $beforeId);
        }

        /** @var array<int, array{organization_id: int}> $rows */
        $rows = $qb->getQuery()->getScalarResult();

        return array_map(fn(array $row) => (int) $row['organization_id'], $rows);
    }

    /**
     * @return Project[]
     */
    public function getProjects(
        int $limit,
        ?int $beforeId = null,
        ?string $search = null,
        ?int $organizationId = null
    ): array {
        $qb = $this->em->getRepository(Project::class)->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        if ($beforeId !== null) {
            $qb->andWhere('p.id < :beforeId')
                ->setParameter('beforeId', $beforeId);
        }

        if ($search !== null) {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($organizationId !== null) {
            $qb->andWhere('p.organization_id = :orgId')
                ->setParameter('orgId', $organizationId);
        }

        /** @var Project[] */
        return $qb->getQuery()->getResult();
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
}
