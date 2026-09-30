<?php

namespace App\Service\Stats\MessageHandler;

use App\Service\Stats\Message\UpdateStatsMessage;
use App\Service\Stats\StatsService;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatsMessageHandler
{
    use ClockAwareTrait;

    public function __construct(private StatsService $statsService)
    {
    }

    public function __invoke(UpdateStatsMessage $message): void
    {
        $feedbackIds = $this->statsService->getUnprocessedFeedbackIds();

        $dates = array_unique([
            $this->now()->format('Y-m-d'),
            $this->now()->modify('-1 day')->format('Y-m-d'),
            ...$this->statsService->getFeedbackDates($feedbackIds),
        ]);

        foreach ($dates as $date) {
            $this->statsService->rebuildDate($date);
        }

        $this->statsService->markFeedbackProcessed($feedbackIds);
    }
}
