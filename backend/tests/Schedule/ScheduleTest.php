<?php

namespace App\Tests\Schedule;

use App\Schedule\GlobalSchedule;
use App\Schedule\ServerSchedule;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{

    // just make sure the objects are created without errors
    public function test_global_schedule(): void
    {
        $schedule = new GlobalSchedule();
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(3, $messages);
    }

    public function test_server_schedule(): void
    {
        $schedule = new ServerSchedule();
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(1, $messages);
    }

}