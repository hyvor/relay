<?php

namespace App\Tests\Api\Sudo\WarmupSchedule;

use App\Api\Sudo\Controller\WarmupScheduleController;
use App\Api\Sudo\Input\UpdateWarmupScheduleInput;
use App\Entity\WarmupSchedule;
use App\Service\Ip\WarmupScheduleService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\WarmupScheduleFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WarmupScheduleController::class)]
#[CoversClass(WarmupScheduleService::class)]
#[CoversClass(UpdateWarmupScheduleInput::class)]
class UpdateWarmupScheduleTest extends WebTestCase
{

    public function test_update_warmup_schedule(): void
    {
        $warmup = WarmupScheduleFactory::createOne(['ip_address' => IpAddressFactory::createOne()]);

        $response = $this->sudoApi(
            'PATCH',
            '/warmup-schedules/' . $warmup->getId(),
            [
                'schedule' => WarmupScheduleService::DEFAULT_SCHEDULE,
            ]
        );

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertEquals(WarmupScheduleService::DEFAULT_SCHEDULE, $json['schedule']);

        $warmupDb = $this->em->getRepository(WarmupSchedule::class)->find($warmup->getId());
        $this->assertNotNull($warmupDb);
        $this->assertEquals(WarmupScheduleService::DEFAULT_SCHEDULE, $warmupDb->getSchedule());
    }

    public function test_update_warmup_schedule_with_decreasing_schedule(): void
    {
        $warmup = WarmupScheduleFactory::createOne(['ip_address' => IpAddressFactory::createOne()]);

        $schedule = WarmupScheduleService::DEFAULT_SCHEDULE;
        $schedule[1] = 0;

        $this->sudoApi(
            'PATCH',
            '/warmup-schedules/' . $warmup->getId(),
            [
                'schedule' => $schedule,
            ]
        );

        $this->assertHasViolation('schedule.schedule', 'Schedule values must not decrease.');
    }

}
