<?php

namespace App\Service\InfrastructureBounce\MessageHandler;

use App\Service\InfrastructureBounce\Message\ClearOldInfrastructureBouncesMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearOldInfrastructureBouncesMessageHandler
{

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(ClearOldInfrastructureBouncesMessage $message): void
    {

        $this->em->createQuery(<<<DQL
            DELETE FROM App\Entity\InfrastructureBounce ib
            WHERE ib.created_at <= :date
        DQL)
            ->setParameter('date', new \DateTimeImmutable('-30 days'))
            ->execute();

    }

}

