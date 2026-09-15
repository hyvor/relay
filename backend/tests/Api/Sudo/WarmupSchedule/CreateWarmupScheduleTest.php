<?php

namespace App\Tests\Api\Sudo\WarmupSchedule;

use App\Api\Sudo\Controller\WarmupScheduleController;
use App\Api\Sudo\Input\CreateWarmupScheduleInput;
use App\Entity\WarmupSchedule;
use App\Service\Ip\WarmupScheduleService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WarmupScheduleController::class)]
#[CoversClass(WarmupScheduleService::class)]
#[CoversClass(CreateWarmupScheduleInput::class)]
class CreateWarmupScheduleTest extends WebTestCase
{

    public function test_create_warmup_schedule(): void
    {
        $ipAddress = IpAddressFactory::createOne();

        $response = $this->sudoApi(
            'POST',
            '/warmup-schedules',
            [
                'ip_address_id' => $ipAddress->getId(),
                'schedule' => WarmupScheduleService::DEFAULT_SCHEDULE,
            ]
        );

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertEquals($ipAddress->getId(), $json['ip_address_id']);
        $this->assertEquals(WarmupScheduleService::DEFAULT_SCHEDULE, $json['schedule']);

        $warmup = $this->em->getRepository(WarmupSchedule::class)->find($json['id']);
        $this->assertNotNull($warmup);
    }

    public function test_create_warmup_schedule_with_decreasing_schedule(): void
    {
        $ipAddress = IpAddressFactory::createOne();

        $schedule = WarmupScheduleService::DEFAULT_SCHEDULE;
        $schedule[1] = 0;

        $this->sudoApi(
            'POST',
            '/warmup-schedules',
            [
                'ip_address_id' => $ipAddress->getId(),
                'schedule' => $schedule,
            ]
        );

        $this->assertHasViolation('schedule.schedule', 'Schedule values must not decrease.');
    }

}
