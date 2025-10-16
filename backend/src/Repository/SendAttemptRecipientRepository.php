<?php

namespace App\Repository;

use App\Entity\SendAttemptRecipient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SendAttemptRecipient>
 */
class SendAttemptRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SendAttemptRecipient::class);
    }
}