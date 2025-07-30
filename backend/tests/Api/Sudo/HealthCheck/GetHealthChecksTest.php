<?php

namespace App\Tests\Api\Sudo\HealthCheck;

use App\Api\Sudo\Controller\HealthCheckController;
use App\Service\Management\Health\HealthCheckService;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\DomainFactory;
use App\Tests\Factory\InstanceFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HealthCheckController::class)]
#[CoversClass(HealthCheckService::class)]
class GetHealthChecksTest extends WebTestCase
{
    public function test_get_health_checks(): void
    {
        $instance = InstanceFactory::createOne(
            [
                'dkim_public_key' => DomainFactory::TEST_DKIM_PUBLIC_KEY,
                'dkim_private_key_encrypted' =>  DomainFactory::TEST_DKIM_PRIVATE_KEY_ENCRYPTED,
                'health_check_results' => [
                    'all_active_ips_have_correct_ptr' => [
                        'passed' => true,
                        'data' => [],
                        'checked_at' => (new \DateTime())->format('c'),
                    ],
                    'all_queues_have_at_least_one_ip' => [
                        'passed' => true,
                        'data' => [],
                        'checked_at' => (new \DateTime())->format('c'),
                    ],
                ]
            ]
        );

        $response = $this->sudoApi(
            'GET',
            '/health-checks',
        );

        $this->assertResponseIsSuccessful();
        $content = $this->getJson();

        $this->assertArrayHasKey('results', $content);
        $this->assertArrayHasKey('last_checked_at', $content);
        $this->assertEquals(
            $instance->getHealthCheckResults(),
            $content['results']
        );
        $this->assertEquals(
            $instance->getLastHealthCheckAt()?->getTimestamp(),
            $content['last_checked_at']
        );
    }
}
