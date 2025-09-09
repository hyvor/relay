<?php

namespace App\Tests\Api;

use App\Api\Console\Metrics\MetricsListener;
use App\Tests\Case\WebTestCase;
use App\Tests\Factory\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;

#[CoversClass(MetricsListener::class)]
class MetricsListenerTest extends WebTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->set(Adapter::class, new InMemory());
    }

    /**
     * @param array<MetricFamilySamples> $metrics
     */
    private function findMetric(array $metrics, string $name): MetricFamilySamples
    {
        foreach ($metrics as $metric) {
            if ($metric->getName() === $name) {
                return $metric;
            }
        }

        $this->fail("Metric $name not found");
    }

    public function test_increments_total_requests(): void
    {
        $project = ProjectFactory::createOne([
            'user_id' => 1,
        ]);

        $listener = $this->getContainer()->get(MetricsListener::class);

        $this->consoleApi(
            $project,
            'GET',
            '/sends/120',
            useSession: true
        );

        $metrics = $listener->getSamples();
        $total = $this->findMetric($metrics,'app_api_http_requests_total');

        $this->assertNotNull($total, 'Expected metric not found');

        $sample = $total->getSamples()[0];

        $this->assertSame('1', $sample->getValue());
        $this->assertSame(['GET', '/api/console/sends/{id}', '403'], $sample->getLabelValues());
    }

    public function test_scrape_metrics(): void
    {
        $response = $this->localApi(
            'GET',
            '/metrics'
        );

        $this->assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('metrics', $content);
        $this->assertStringContainsString('# HELP php_info Information about the PHP environment.', $content['metrics']);
    }
}
