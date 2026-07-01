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
            'warmup_status' => WarmupStatus::WARMING,
        ]);

        $now = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));

        foreach ($schedules as $schedule) {
            $startedDate = $schedule->getWarmupStartedDate();
            $plan = $schedule->getWarmupSchedule();

            if ($startedDate === null || $plan === null) {
                continue;
            }

            $schedule->setWarmupSentToday(0);

            $dayIndex = (int) $startedDate->setTime(0, 0)->diff($now)->days;

            if ($dayIndex >= 30) {
                $schedule->setWarmupStatus(WarmupStatus::WARMED);
                $schedule->setWarmupMaxToday(0);
            } else {
                $schedule->setWarmupMaxToday($plan[$dayIndex] ?? 0);
            }

            $this->em->persist($schedule);
        }

        $this->em->flush();
    }
}
