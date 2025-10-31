<?php

namespace App\Repository;

use App\Entity\InfrastructureBounce;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<InfrastructureBounce>
 */
class InfrastructureBounceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InfrastructureBounce::class);
    }
}
