<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Instance\InstanceService;
use App\Service\Stats\Message\UpdateStatsMessage;
use App\Service\Stats\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatsMessageHandler
{
    use ClockAwareTrait;

    public const string LOCK_NAME = "stats_update";

    /**
     * Sends are deleted after 30 days (ClearExpiredSendsMessageHandler). A day can only be re-counted
     * while all of its sends still exist. The cleanup deletes sends created at or before "now - 30 days",
     * so the oldest day may already be partially deleted, and a 2-day margin keeps a day that is being
     * re-counted from losing rows half-way (the cleanup runs on a schedule, not at midnight).
     */
    public const int MAX_REBUILD_AGE_DAYS = 30 - 2;

    public function __construct(
        private StatsService $statsService,
        private InstanceService $instanceService,
        private EntityManagerInterface $em,
        private LockFactory $lockFactory,
    ) {}

    public function __invoke(UpdateStatsMessage $message): void
    {
        $lock = $this->lockFactory->createLock(self::LOCK_NAME);

        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->update();
        } finally {
            $lock->release();
        }
    }

    private function update(): void
    {
        $now = $this->now();
        $today = $now->format("Y-m-d");
        $minDate = $now
            ->modify("-" . self::MAX_REBUILD_AGE_DAYS . " days")
            ->format("Y-m-d");

        $instance = $this->instanceService->getInstance();

        $from =
            $instance->getStatsRebuiltAt()?->format("Y-m-d") ??
            $now->modify("-1 day")->format("Y-m-d");

        $dates = [
            ...$this->dateRange(max($from, $minDate), $today),
            ...$this->statsService->getOutdatedDates($minDate),
        ];

        $dates = array_unique($dates);
        sort($dates);

        foreach ($dates as $date) {
            $this->statsService->rebuildDate($date);
        }

        $instance->setStatsRebuiltAt($now);
        $this->em->flush();
    }

    /**
     * @return string[]
     */
    private function dateRange(string $from, string $to): array
    {
        $dates = [];

        for ($date = new \DateTimeImmutable($from); $date->format("Y-m-d") <= $to; $date = $date->modify("+1 day")) {
            $dates[] = $date->format("Y-m-d");
        }

        return $dates;
    }
}
