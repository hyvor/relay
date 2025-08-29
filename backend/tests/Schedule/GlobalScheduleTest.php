<?php

namespace App\Tests\Schedule;

use App\Entity\Type\DomainStatus;
use App\Schedule\GlobalSchedule;
use App\Service\Domain\Message\ReverifyDomainsMessage;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Hyvor\Internal\Bundle\Testing\Scheduler\SchedulerTestingTrait;

#[CoversClass(GlobalSchedule::class)]
class GlobalScheduleTest extends WebTestCase
{

    use SchedulerTestingTrait;

    // just make sure the objects are created without errors
    public function test_global_schedule(): void
    {

        $before = ($this->em->getConnection()->executeQuery('SELECT pid,
       usename AS user,
       datname AS database,
       client_addr,
       client_port,
       application_name,
       backend_start,
       state,
       query
FROM pg_stat_activity
ORDER BY backend_start;')->fetchAllKeyValue());

        $schedule = new GlobalSchedule($this->createMock(LockFactory::class));
        $s = $schedule->getSchedule();
        $messages = $s->getRecurringMessages();
        $this->assertCount(6, $messages);

        $verifyDomainMessages = $this->getMessagesOfType($schedule, ReverifyDomainsMessage::class);
        $this->assertCount(2, $verifyDomainMessages);
        $this->assertSame([DomainStatus::ACTIVE, DomainStatus::WARNING], $verifyDomainMessages[0]->getStatuses());
        $this->assertSame([DomainStatus::PENDING], $verifyDomainMessages[1]->getStatuses());

        $after = ($this->em->getConnection()->executeQuery('SELECT pid,
       usename AS user,
       datname AS database,
       client_addr,
       client_port,
       application_name,
       backend_start,
       state,
       query
FROM pg_stat_activity
ORDER BY backend_start;')->fetchAllKeyValue());
        dd($before, $after);
        dd(array_diff($after, $before));
    }


}
