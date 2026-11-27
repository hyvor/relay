<?php

namespace App\Service\Cloud\MeteredUsage\MessageHandler;

use App\Service\Cloud\MeteredUsage\Message\ReportMeteredUsageMessage;
use App\Service\Cloud\MeteredUsage\MeteredUsageService;
use App\Service\Send\MessageHandler\ClearExpiredSendsMessageHandler;
use Hyvor\Internal\InternalConfig;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ReportMeteredUsageMessageHandler
{

    use ClockAwareTrait;

    /**
     * Y-m-d of the last day whose usage was reported
     */
    public const string LAST_REPORTED_DAY_CACHE_KEY = 'cloud.metered_usage.last_reported_day';

    /**
     * Sends older than the retention period are deleted, so their usage can no longer be counted.
     */
    public const int MAX_CATCH_UP_DAYS = ClearExpiredSendsMessageHandler::RETENTION_DAYS - 1;

    public function __construct(
        private InternalConfig $internalConfig,
        private MeteredUsageService $meteredUsageService,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ReportMeteredUsageMessage $message): void
    {
        if (!$this->internalConfig->getDeployment()->isCloud()) {
            return;
        }

        $yesterday = $this->now()->setTime(0, 0)->modify('-1 day');
        $earliest = $yesterday->modify('-' . (self::MAX_CATCH_UP_DAYS - 1) . ' days');

        $cursor = $this->cache->getItem(self::LAST_REPORTED_DAY_CACHE_KEY);
        $lastReportedDay = $cursor->isHit() && is_string($cursor->get())
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $cursor->get())
            : false;

        // first run (or lost cursor): start from yesterday
        $day = $lastReportedDay !== false ? $lastReportedDay->modify('+1 day') : $yesterday;

        if ($day < $earliest) {
            $this->logger->error('Metered usage: days missed beyond the send retention period cannot be reported', [
                'from' => $day->format('Y-m-d'),
                'to' => $earliest->modify('-1 day')->format('Y-m-d'),
            ]);
            $day = $earliest;
        }

        while ($day <= $yesterday) {
            $count = $this->meteredUsageService->reportDailyUsage($day);

            // move the cursor only after the day's events are queued
            $cursor->set($day->format('Y-m-d'));
            $this->cache->save($cursor);

            $this->logger->info('Metered usage reported', [
                'date' => $day->format('Y-m-d'),
                'organizations' => $count,
            ]);

            $day = $day->modify('+1 day');
        }
    }

}
