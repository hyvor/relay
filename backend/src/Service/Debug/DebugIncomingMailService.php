<?php

namespace App\Service\Debug;

use App\Entity\DebugIncomingEmail;
use Doctrine\ORM\EntityManagerInterface;

class DebugIncomingMailService
{

    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    /**
     * @return array<DebugIncomingEmail>
     */
    public function getIncomingMails(int $limit, int $offset): array
    {
        return $this->em->getRepository(DebugIncomingEmail::class)->findBy(
            [],
            ['id' => 'DESC'],
            $limit,
            $offset
        );
    }

}