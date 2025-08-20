<?php

namespace App\Tests\Schedule;

use App\Entity\Type\DomainStatus;
use App\Schedule\GlobalSchedule;
use App\Schedule\ServerSchedule;
use App\Service\Domain\Message\ReverifyDomainsMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

#[CoversClass(GlobalSchedule::class)]
#[CoversClass(ServerSchedule::class)]
class ScheduleTest extends TestCase
{
    public function test_server_schedule(): void
    {
        $schedule = new ServerSchedule();
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(2, $messages);
    }

}
