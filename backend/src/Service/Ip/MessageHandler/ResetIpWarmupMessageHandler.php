<?php

namespace App\Service\Ip\MessageHandler;

use App\Entity\Type\WarmupStatus;
use App\Entity\WarmupSchedule;
use App\Service\Ip\Message\ResetIpWarmupMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ResetIpWarmupMessageHandler
{

    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(ResetIpWarmupMessage $_message): void
    {
        /** @var WarmupSchedule[] $schedules */
        $schedules = $this->em->getRepository(WarmupSchedule::class)->findBy([
            'status' => WarmupStatus::WARMING,
        ]);

        $now = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        foreach ($schedules as $schedule) {
            $startedDate = $schedule->getStartedDate();
            $plan = $schedule->getSchedule();

            $schedule->appendResult($schedule->getSentToday());

            $schedule->setSentToday(0);

            $dayIndex = (int) $startedDate->setTime(0, 0)->diff($now)->days;

            if ($dayIndex >= 30) {
                $schedule->setStatus(WarmupStatus::WARMED);
                $schedule->setMaxToday(0);
            } else {
                $schedule->setMaxToday($plan[$dayIndex] ?? 0);
            }

            $schedule->setUpdatedAt($now);
            $this->em->persist($schedule);
        }

        $this->em->flush();
    }
}
