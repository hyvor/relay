<?php

namespace App\Schedule;

use App\Service\Idempotency\Message\ClearExpiredIdempotencyRecordsMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for global tasks
 * Lock is used, not stateful
 */
#[AsSchedule(name: 'global')]
class GlobalSchedule implements ScheduleProviderInterface
{

    public function __construct(
        private LockFactory $lockFactory
    )
    {
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->add(RecurringMessage::every('1 hour', new ClearExpiredIdempotencyRecordsMessage));
            // ->lock($this->lockFactory->createLock('global-schedule', 20));
    }

}