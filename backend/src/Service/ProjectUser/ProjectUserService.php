<?php

namespace App\Service\ProjectUser;

use App\Entity\Project;
use App\Entity\ProjectUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;

class ProjectUserService
{

    use ClockAwareTrait;

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * @return ProjectUser[]
     */
    public function getProjectsOfUser(int $userId): array
    {
        return $this->em->getRepository(ProjectUser::class)->findBy(['user_id' => $userId]);
    }

    /**
     * @return ProjectUser[]
     */
    public function getProjectUsers(Project $project): array
    {
        return $this->em->getRepository(ProjectUser::class)->findBy(['project' => $project]);
    }

    public function getProjectUser(Project $project, int $userId): ?ProjectUser
    {
        return $this->em->getRepository(ProjectUser::class)
            ->findOneBy(['project' => $project, 'user_id' => $userId]);
    }

    /**
     * @param string[] $scopes
     */
    public function createProjectUser(
        Project $project,
        int $userId,
        array $scopes = [],
        bool $flush = true
    ): ProjectUser {
        $projectUser = new ProjectUser();
        $projectUser->setCreatedAt($this->now());
        $projectUser->setUpdatedAt($this->now());
        $projectUser->setProject($project);
        $projectUser->setUserId($userId);
        $projectUser->setScopes($scopes);

        $this->em->persist($projectUser);

        if ($flush) {
            $this->em->flush();
        }

        return $projectUser;
    }

    public function deleteAllProjectUsers(Project $project): void
    {
        $query = $this->em->createQuery(
            'DELETE FROM App\Entity\ProjectUser pu WHERE pu.project = :project'
        );
        $query->setParameter('project', $project);
        $query->execute();
    }

    public function deleteProjectUser(ProjectUser $projectUser): void
    {
        $this->em->remove($projectUser);
        $this->em->flush();
    }
}
