<?php

namespace App\Tests\Schedule;

use App\Entity\Type\DomainStatus;
use App\Schedule\DefaultSchedule;
use App\Service\Domain\Message\ReverifyDomainsMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Hyvor\Internal\Bundle\Testing\Scheduler\SchedulerTestingTrait;
use Symfony\Contracts\Cache\CacheInterface;

#[CoversClass(DefaultSchedule::class)]
class GlobalScheduleTest extends TestCase
{

    use SchedulerTestingTrait;

    // just make sure the objects are created without errors
    public function test_global_schedule(): void
    {
        $schedule = new DefaultSchedule(
            $this->createMock(LockFactory::class), $this->createMock(CacheInterface::class)
        );
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(8, $messages);

        $verifyDomainMessages = $this->getMessagesOfType($schedule, ReverifyDomainsMessage::class);
        $this->assertCount(2, $verifyDomainMessages);
        $this->assertSame([DomainStatus::ACTIVE, DomainStatus::WARNING], $verifyDomainMessages[0]->getStatuses());
        $this->assertSame([DomainStatus::PENDING], $verifyDomainMessages[1]->getStatuses());
    }


}