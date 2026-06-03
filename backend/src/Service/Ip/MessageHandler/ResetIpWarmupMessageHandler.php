<?php

namespace App\Service\Ip\MessageHandler;

use App\Entity\IpAddress;
use App\Entity\Type\WarmupStatus;
use App\Service\Ip\Message\ResetIpWarmupMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ResetIpWarmupMessageHandler
{

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ResetIpWarmupMessage $message): void
    {
        /** @var IpAddress[] $warmingIps */
        $warmingIps = $this->em->getRepository(IpAddress::class)->findBy([
            'warmup_status' => WarmupStatus::WARMING,
        ]);

        $now = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        foreach ($warmingIps as $ip) {
            $startedDate = $ip->getWarmupStartedDate();
            $schedule = $ip->getWarmupSchedule();

            if ($startedDate === null || $schedule === null) {
                continue;
            }

            $ip->setWarmupSentToday(0);

            $dayIndex = (int) $startedDate->diff($now)->days;

            if ($dayIndex >= 30) {
                $ip->setWarmupStatus(WarmupStatus::WARMED);
                $ip->setWarmupMaxToday(0);
            } else {
                $ip->setWarmupMaxToday($schedule[$dayIndex] ?? 0);
            }

            $this->em->persist($ip);
        }

        $this->em->flush();
    }

}
