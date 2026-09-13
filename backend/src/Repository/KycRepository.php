<?php

namespace App\Repository;

use App\Entity\Kyc;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Kyc>
 */
class KycRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kyc::class);
    }

    public function findOneByOrganizationId(int $organizationId): ?Kyc
    {
        return $this->findOneBy(['organization_id' => $organizationId]);
    }
}
