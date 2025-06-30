<?php

namespace App\Service\Idempotency;

use App\Entity\ApiIdempotencyRecord;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

class IdempotencyService
{

    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function getIdempotencyRecordByProjectAndKey(Project $project, string $key): ?ApiIdempotencyRecord
    {
        return $this->em->getRepository(ApiIdempotencyRecord::class)
            ->findOneBy(['project' => $project, 'key' => $key]);
    }

}