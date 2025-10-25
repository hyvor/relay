<?php

namespace App\Tests\Api\Local;

use App\Api\Local\Controller\LocalController;
use App\Tests\Case\WebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LocalController::class)]
class GetMetricsTest extends WebTestCase
{

    public function test_prometheus_metrics(): void
    {
        $response = $this->localApi("GET", "/metrics");

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');

        $content = (string)$response->getContent();
        $this->assertStringContainsString('# HELP ', $content);
        $this->assertStringContainsString('# TYPE ', $content);
    }

}