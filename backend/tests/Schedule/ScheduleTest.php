<?php

namespace App\Tests\Schedule;

use App\Schedule\GlobalSchedule;
use App\Schedule\ServerSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(GlobalSchedule::class)]
#[CoversClass(ServerSchedule::class)]
class ScheduleTest extends TestCase
{

    // just make sure the objects are created without errors
    public function test_global_schedule(): void
    {
        $schedule = new GlobalSchedule($this->createMock(LockFactory::class));
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(5, $messages);
    }

    public function test_server_schedule(): void
    {
        $schedule = new ServerSchedule();
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(1, $messages);
    }

}