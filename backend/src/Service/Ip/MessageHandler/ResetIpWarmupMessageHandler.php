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
            $dayIndex = (int)$schedule->getStartedDate()->setTime(0, 0)->diff($now)->days;

            // idempotency (skip if already processed for today)
            if ($dayIndex > 0 && count($schedule->getResults()) >= $dayIndex) {
                continue;
            }

            $plan = $schedule->getSchedule();

            $schedule->appendResult($schedule->getSentToday());
            $schedule->setSentToday(0);

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
