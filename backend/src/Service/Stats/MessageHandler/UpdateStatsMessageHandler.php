<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Send\MessageHandler\ClearExpiredSendsMessageHandler;
use App\Service\Stats\Message\UpdateStatsMessage;
use App\Service\Stats\StatsService;
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
        $feedbackIds = $this->statsService->getUnprocessedFeedbackIds();
        $minDate = $this->now()
            ->modify("-" . self::MAX_REBUILD_AGE_DAYS . " days")
            ->format("Y-m-d");

        $dates = array_unique([
            $this->now()->format("Y-m-d"),
            $this->now()->modify("-1 day")->format("Y-m-d"),
            ...$this->statsService->getFeedbackDates($feedbackIds),
        ]);

        foreach ($dates as $date) {
            if ($date < $minDate) {
                continue;
            }

            $this->statsService->rebuildDate($date);
        }

        $this->statsService->markFeedbackProcessed($feedbackIds);
    }
}
