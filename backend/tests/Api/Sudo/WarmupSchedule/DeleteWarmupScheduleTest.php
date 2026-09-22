<?php

namespace App\Tests\Api\Sudo\WarmupSchedule;

use App\Api\Sudo\Controller\WarmupScheduleController;
use App\Entity\WarmupSchedule;
use App\Service\Ip\WarmupScheduleService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\IpAddressFactory;
use App\Tests\Factory\WarmupScheduleFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WarmupScheduleController::class)]
#[CoversClass(WarmupScheduleService::class)]
class DeleteWarmupScheduleTest extends WebTestCase
{

    public function test_delete_warmup_schedule(): void
    {
        $warmup = WarmupScheduleFactory::createOne(['ip_address' => IpAddressFactory::createOne()]);
        $warmupId = $warmup->getId();

        $response = $this->sudoApi('DELETE', '/warmup-schedules/' . $warmupId);

        $this->assertResponseIsSuccessful();

        $json = $this->getJson();
        $this->assertTrue($json['success']);

        $warmupDb = $this->em->getRepository(WarmupSchedule::class)->find($warmupId);
        $this->assertNull($warmupDb);
    }

}
