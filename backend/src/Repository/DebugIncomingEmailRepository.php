<?php

namespace App\Repository;

use App\Entity\DebugIncomingEmail;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<DebugIncomingEmail>
 */
class DebugIncomingEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DebugIncomingEmail::class);
    }
}
