<?php

namespace App\Service\Queue;

use App\Entity\Queue;
use Doctrine\ORM\EntityManagerInterface;

class QueueService
{

    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    /**
     * @return Queue[]
     */
    public function getAllQueues(): array
    {
        return $this->em->getRepository(Queue::class)->findAll();
    }

}
