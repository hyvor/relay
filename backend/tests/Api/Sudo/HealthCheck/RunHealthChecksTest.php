<?php

namespace App\Tests\Api\Sudo\HealthCheck;

use App\Api\Sudo\Controller\HealthCheckController;
use App\Service\Management\Health\HealthCheckService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthCheckController::class)]
#[CoversClass(HealthCheckService::class)]
class RunHealthChecksTest extends WebTestCase
{
    public function test_run_health_checks(): void
    {
        $response = $this->sudoApi(
            'POST',
            '/health-checks',
        );
        $this->assertResponseIsSuccessful();
        $content = $this->getJson();

        $this->assertArrayHasKey('last_checked_at', $content);
        $this->assertArrayHasKey('results', $content);
        $this->assertNotEmpty($content['results']);
        $this->assertIsArray($content['results']);
        $this->assertArrayHasKey('all_active_ips_have_correct_ptr', $content['results']);
        $this->assertArrayHasKey('all_queues_have_at_least_one_ip', $content['results']);
    }
}
