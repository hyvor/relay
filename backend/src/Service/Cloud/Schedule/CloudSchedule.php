<?php

namespace App\Service\Cloud\Schedule;

use App\Service\Cloud\MeteredUsage\Message\ReportMeteredUsageMessage;
use Hyvor\Internal\InternalConfig;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Schedule for cloud-only tasks
 */
#[AsSchedule(name: 'cloud')]
class CloudSchedule implements ScheduleProviderInterface
{

    public function __construct(
        private InternalConfig $internalConfig,
        private LockFactory $lockFactory,
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $schedule = new SymfonySchedule();

        if (!$this->internalConfig->getDeployment()->isCloud()) {
            return $schedule;
        }

        return $schedule
            // metered usage: reports every day
            ->add(RecurringMessage::cron('15 0 * * *', new ReportMeteredUsageMessage()))

            // cloud lock
            ->lock($this->lockFactory->createLock('cloud-schedule', 20))

            // stateful
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
