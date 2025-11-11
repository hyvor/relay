<?php

namespace App\Schedule;

use App\Entity\Type\DomainStatus;
use App\Service\Domain\Message\PurgeStalePendingSuspendedDomainsMessage;
use App\Service\Domain\Message\ReverifyDomainsMessage;
use App\Service\Idempotency\Message\ClearExpiredIdempotencyRecordsMessage;
use App\Service\InfrastructureBounce\Message\ClearOldInfrastructureBouncesMessage;
use App\Service\Management\Message\RunHealthChecksMessage;
use App\Service\Send\Message\ClearExpiredSendsMessage;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Schedule for global tasks
 * Lock is used, not stateful
 */
#[AsSchedule()]
class DefaultSchedule implements ScheduleProviderInterface
{

    public function __construct(
        private LockFactory $lockFactory,
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            // infra
            ->add(RecurringMessage::every('1 hour', new RunHealthChecksMessage))

            // api
            ->add(RecurringMessage::every('1 hour', new ClearExpiredIdempotencyRecordsMessage))

            // sends
            ->add(RecurringMessage::every('1 day', new ClearExpiredSendsMessage))

            // infrastructure bounces
            ->add(RecurringMessage::every('1 day', new ClearOldInfrastructureBouncesMessage))

            // domain verification

            // reverify active and warning domains every day
            ->add(
                RecurringMessage::every(
                    '1 day',
                    new ReverifyDomainsMessage([DomainStatus::ACTIVE, DomainStatus::WARNING])
                )
            )
            // reverify pending domains every 5 minutes
            ->add(
                RecurringMessage::every(
                    '5 minutes',
                    new ReverifyDomainsMessage([DomainStatus::PENDING])
                )
            )
            ->add(RecurringMessage::every('1 hour', new PurgeStalePendingSuspendedDomainsMessage))

            // telemetry
            ->add(RecurringMessage::every('24 hours', new RunCommandMessage('self-hosted:record-telemetry')))

            // global lock
            ->lock($this->lockFactory->createLock('global-schedule', 20))

            // stateful
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
