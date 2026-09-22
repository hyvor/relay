<?php

namespace App\Tests\Api\Sudo\WarmupSchedule;

use App\Api\Sudo\Controller\WarmupScheduleController;
use App\Service\Ip\WarmupScheduleService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\WarmupScheduleFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WarmupScheduleController::class)]
#[CoversClass(WarmupScheduleService::class)]
class GetWarmupSchedulesTest extends WebTestCase
{

    public function test_get_warmup_schedules(): void
    {
        $ipAddress = IpAddressFactory::createOne();
        WarmupScheduleFactory::createOne(['ip_address' => $ipAddress]);
        WarmupScheduleFactory::createOne(['ip_address' => IpAddressFactory::createOne()]);

        $this->sudoApi('GET', '/warmup-schedules?ip_address_id=' . $ipAddress->getId());

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertCount(1, $json);

        $item = $json[0];
        $this->assertIsArray($item);
        $this->assertEquals($ipAddress->getId(), $item['ip_address_id']);
        $this->assertEquals($ipAddress->getIpAddress(), $item['ip_address']);
    }

}
