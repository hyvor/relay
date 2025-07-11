<?php

namespace App\Service\Send\MessageHandler;

use App\Service\Send\Message\ClearExpiredSendsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearExpiredSendsMessageHandler
{

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(ClearExpiredSendsMessage $message): void
    {

        $this->em->createQuery(<<<DQL
            DELETE FROM App\Entity\Send s
            WHERE s.created_at <= :date
        DQL)
            ->setParameter('date', new \DateTimeImmutable('-1 month'))
            ->execute();

    }

}