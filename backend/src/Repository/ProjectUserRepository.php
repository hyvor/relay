<?php

namespace App\Repository;

use App\Entity\ProjectUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectUser>
 */
class ProjectUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectUser::class);
	}

    /**
     * @return ProjectUser[]
     */
	public function findByUserAndOrganization(int $userId, int $orgId): array
	{
		/** @var ProjectUser[] $result */
		$result = $this->createQueryBuilder('pu')
			->innerJoin('pu.project', 'p')
			->andWhere('pu.user_id = :userId')
			->andWhere('p.organization_id = :orgId')
			->setParameter('userId', $userId)
			->setParameter('orgId', $orgId)
			->getQuery()
			->getResult();

		return $result;
	}

}
