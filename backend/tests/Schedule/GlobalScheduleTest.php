<?php

namespace App\Tests\Schedule;

use App\Entity\Type\DomainStatus;
use App\Schedule\GlobalSchedule;
use App\Service\Domain\Message\ReverifyDomainsMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Hyvor\Internal\Bundle\Testing\Scheduler\SchedulerTestingTrait;

#[CoversClass(GlobalSchedule::class)]
class GlobalScheduleTest extends TestCase
{

    use SchedulerTestingTrait;

    // just make sure the objects are created without errors
    public function test_global_schedule(): void
    {
        $schedule = new GlobalSchedule($this->createMock(LockFactory::class));
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(7, $messages);

        $verifyDomainMessages = $this->getMessagesOfType($schedule, ReverifyDomainsMessage::class);
        $this->assertCount(2, $verifyDomainMessages);
        $this->assertSame([DomainStatus::ACTIVE, DomainStatus::WARNING], $verifyDomainMessages[0]->getStatuses());
        $this->assertSame([DomainStatus::PENDING], $verifyDomainMessages[1]->getStatuses());
    }


}