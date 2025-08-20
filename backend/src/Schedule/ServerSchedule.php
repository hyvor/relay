<?php

namespace App\Schedule;

use App\Service\Management\Message\PingMessage;
use App\Service\Management\Message\ServerTaskMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule for server-scoped tasks
 * No lock is used, not stateful
 */
#[AsSchedule(name: 'server')]
class ServerSchedule implements ScheduleProviderInterface
{
    public function __construct() {
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->add(
                RecurringMessage::every('30 seconds', new PingMessage())
            )
            ->add(
                RecurringMessage::every('1 minute', new ServerTaskMessage())
            )
        ;
    }
}
