<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Instance\InstanceService;
use App\Service\Send\MessageHandler\ClearExpiredSendsMessageHandler;
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
    public const int MAX_REBUILD_AGE_DAYS = ClearExpiredSendsMessageHandler::RETENTION_DAYS - 2;

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

        $feedbackIds = $this->statsService->getUnprocessedFeedbackIds();

        $dates = [
            ...$this->dateRange(max($from, $minDate), $today),
            ...$this->statsService->assignFeedbackStatDates($feedbackIds),
        ];

        $dates = array_filter(array_unique($dates), fn(string $date) => $date >= $minDate);
        sort($dates);

        foreach ($dates as $date) {
            $this->statsService->rebuildDate($date);
        }

        $this->statsService->markFeedbackProcessed($feedbackIds);

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
