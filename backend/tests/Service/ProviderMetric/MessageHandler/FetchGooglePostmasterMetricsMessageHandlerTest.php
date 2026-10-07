<?php

namespace App\Tests\Service\ProviderMetric\MessageHandler;

use App\Entity\ProviderMetric;
use App\Entity\Type\ProviderMetricSource;
use App\Service\ProviderMetric\GooglePostmasterClient;
use App\Service\ProviderMetric\Message\FetchGooglePostmasterMetricsMessage;
use App\Service\ProviderMetric\MessageHandler\FetchGooglePostmasterMetricsMessageHandler;
use App\Tests\Case\KernelTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProviderMetricFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(FetchGooglePostmasterMetricsMessageHandler::class)]
#[CoversClass(GooglePostmasterClient::class)]
class FetchGooglePostmasterMetricsMessageHandlerTest extends KernelTestCase
{

    /**
     * @var array<array{method: string, url: string, options: array<mixed>}>
     */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Clock::set(new MockClock('2026-06-15 12:00:00'));

        $this->setConfig('googlePostmasterClientId', 'client-id');
        $this->setConfig('googlePostmasterClientSecret', 'client-secret');
        $this->setConfig('googlePostmasterRefreshToken', 'refresh-token');
    }

    /**
     * @param MockResponse[] $statsResponses
     */
    private function mockHttp(array $statsResponses): void
    {
        $this->container->set(
            HttpClientInterface::class,
            new MockHttpClient(function (string $method, string $url, array $options) use (&$statsResponses) {
                $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

                if ($url === GooglePostmasterClient::TOKEN_URL) {
                    return new JsonMockResponse(['access_token' => 'access-token']);
                }

                return array_shift($statsResponses) ?? new MockResponse('', ['http_code' => 500]);
            })
        );
    }

    /**
     * @param array<string, float> $rates
     * @return array<string, mixed>
     */
    private function statsResponse(array $rates, ?string $nextPageToken = null): array
    {
        $domainStats = [];

        foreach ($rates as $date => $rate) {
            [$year, $month, $day] = array_map('intval', explode('-', $date));
            $domainStats[] = [
                'metric' => 'spam_rate',
                'date' => ['year' => $year, 'month' => $month, 'day' => $day],
                'value' => ['floatValue' => $rate],
            ];
        }

        return array_filter(['domainStats' => $domainStats, 'nextPageToken' => $nextPageToken]);
    }

    /**
     * @param array<string, list<string>> $idsByDate
     * @return array<string, mixed>
     */
    private function feedbackLoopIdsResponse(array $idsByDate): array
    {
        $domainStats = [];

        foreach ($idsByDate as $date => $ids) {
            [$year, $month, $day] = array_map('intval', explode('-', $date));
            $domainStats[] = [
                'metric' => 'feedback_loop_id',
                'date' => ['year' => $year, 'month' => $month, 'day' => $day],
                'value' => ['stringList' => ['values' => $ids]],
            ];
        }

        return ['domainStats' => $domainStats];
    }

    /**
     * @param array<string, array<int|string, float>> $ratesByDate
     * @return array<string, mixed>
     */
    private function feedbackLoopRatesResponse(array $ratesByDate): array
    {
        $domainStats = [];

        foreach ($ratesByDate as $date => $rates) {
            [$year, $month, $day] = array_map('intval', explode('-', $date));
            foreach ($rates as $id => $rate) {
                $domainStats[] = [
                    'metric' => "fbl_$id",
                    'date' => ['year' => $year, 'month' => $month, 'day' => $day],
                    'value' => ['floatValue' => $rate],
                ];
            }
        }

        return ['domainStats' => $domainStats];
    }

    private function runHandler(): void
    {
        /** @var FetchGooglePostmasterMetricsMessageHandler $handler */
        $handler = $this->container->get(FetchGooglePostmasterMetricsMessageHandler::class);
        $handler(new FetchGooglePostmasterMetricsMessage());
    }

    /**
     * @return array<mixed>
     */
    private function requestBody(int $index): array
    {
        $body = $this->requests[$index]['options']['body'];
        $this->assertIsString($body);

        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function test_stores_daily_spam_rates(): void
    {
        $this->mockHttp([
            new JsonMockResponse($this->statsResponse(['2026-06-12' => 0.0012], 'page-2')),
            new JsonMockResponse($this->statsResponse(['2026-06-13' => 0.003])),
            new JsonMockResponse([]),
        ]);

        $this->runHandler();

        $metrics = $this->em->getRepository(ProviderMetric::class)->findBy([], ['metric_date' => 'ASC']);
        $this->assertCount(2, $metrics);

        $this->assertSame(ProviderMetricSource::GOOGLE, $metrics[0]->getSource());
        $this->assertNull($metrics[0]->getProject());
        $this->assertNull($metrics[0]->getIpAddress());
        $this->assertNull($metrics[0]->getProcessedAt());
        $this->assertSame('2026-06-12', $metrics[0]->getMetricDate()->format('Y-m-d'));
        $this->assertSame(0.0012, (float) $metrics[0]->getValue());
        $this->assertSame('2026-06-13', $metrics[1]->getMetricDate()->format('Y-m-d'));
        $this->assertSame(0.003, (float) $metrics[1]->getValue());

        $this->assertSame(GooglePostmasterClient::TOKEN_URL, $this->requests[0]['url']);
        $tokenBody = $this->requests[0]['options']['body'];
        $this->assertIsString($tokenBody);
        parse_str($tokenBody, $tokenParams);
        $this->assertSame([
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'refresh-token',
            'grant_type' => 'refresh_token',
        ], $tokenParams);

        $this->assertSame(
            GooglePostmasterClient::API_URL . '/domains/mail.hyvor-relay.com/domainStats:query',
            $this->requests[1]['url']
        );
        $headers = $this->requests[1]['options']['headers'];
        $this->assertIsArray($headers);
        $this->assertContains('Authorization: Bearer access-token', $headers);

        $body = $this->requestBody(1);
        $this->assertSame('domains/mail.hyvor-relay.com', $body['parent']);
        $this->assertSame([['name' => 'spam_rate', 'baseMetric' => ['standardMetric' => 'SPAM_RATE']]], $body['metricDefinitions']);
        $this->assertSame(['dateRanges' => ['dateRanges' => [[
            'start' => ['year' => 2026, 'month' => 6, 'day' => 8],
            'end' => ['year' => 2026, 'month' => 6, 'day' => 15],
        ]]]], $body['timeQuery']);
        $this->assertArrayNotHasKey('pageToken', $body);

        $this->assertSame('page-2', $this->requestBody(2)['pageToken']);
    }

    public function test_only_stores_changed_values(): void
    {
        ProviderMetricFactory::createOne([
            'metric_date' => new \DateTimeImmutable('2026-06-12'),
            'value' => '0.0012',
        ]);
        ProviderMetricFactory::createOne([
            'metric_date' => new \DateTimeImmutable('2026-06-13'),
            'value' => '0.003',
        ]);

        $this->mockHttp([
            new JsonMockResponse($this->statsResponse([
                '2026-06-12' => 0.0012,
                '2026-06-13' => 0.004,
                '2026-06-14' => 0.001,
            ])),
            new JsonMockResponse([]),
        ]);

        $this->runHandler();

        $metrics = $this->em->getRepository(ProviderMetric::class)->findBy([], ['id' => 'ASC']);
        $this->assertCount(4, $metrics);
        $this->assertSame('2026-06-13', $metrics[2]->getMetricDate()->format('Y-m-d'));
        $this->assertSame(0.004, (float) $metrics[2]->getValue());
        $this->assertSame('2026-06-14', $metrics[3]->getMetricDate()->format('Y-m-d'));
        $this->assertSame(0.001, (float) $metrics[3]->getValue());
    }

    public function test_stores_feedback_loop_spam_rates_per_project(): void
    {
        $project = ProjectFactory::createOne();
        $projectId = (string) $project->getId();

        $this->mockHttp([
            new JsonMockResponse([]),
            new JsonMockResponse($this->feedbackLoopIdsResponse([
                '2026-06-12' => [$projectId, 'hyvorrelay', '999999'],
                '2026-06-13' => [$projectId],
            ])),
            new JsonMockResponse($this->feedbackLoopRatesResponse([
                '2026-06-12' => [$projectId => 0.002, 'hyvorrelay' => 0.001, '999999' => 0.5],
                '2026-06-13' => [$projectId => 0.004],
            ])),
        ]);

        $this->runHandler();

        $metrics = $this->em->getRepository(ProviderMetric::class)->findBy([], ['metric_date' => 'ASC']);
        $this->assertCount(2, $metrics);
        $this->assertSame($project->getId(), $metrics[0]->getProject()?->getId());
        $this->assertNull($metrics[0]->getIpAddress());
        $this->assertSame('2026-06-12', $metrics[0]->getMetricDate()->format('Y-m-d'));
        $this->assertSame(0.002, (float) $metrics[0]->getValue());
        $this->assertSame('2026-06-13', $metrics[1]->getMetricDate()->format('Y-m-d'));
        $this->assertSame(0.004, (float) $metrics[1]->getValue());

        $this->assertSame([[
            'name' => 'feedback_loop_id',
            'baseMetric' => ['standardMetric' => 'FEEDBACK_LOOP_ID'],
            'filter' => 'aggregation_key_type = "ALL_DKIM"',
        ]], $this->requestBody(3)['metricDefinitions']);

        $this->assertSame([
            [
                'name' => "fbl_$projectId",
                'baseMetric' => ['standardMetric' => 'FEEDBACK_LOOP_SPAM_RATE'],
                'filter' => "feedback_loop_id = \"$projectId\" AND aggregation_key_type = \"ALL_DKIM\"",
            ],
            [
                'name' => 'fbl_hyvorrelay',
                'baseMetric' => ['standardMetric' => 'FEEDBACK_LOOP_SPAM_RATE'],
                'filter' => 'feedback_loop_id = "hyvorrelay" AND aggregation_key_type = "ALL_DKIM"',
            ],
            [
                'name' => 'fbl_999999',
                'baseMetric' => ['standardMetric' => 'FEEDBACK_LOOP_SPAM_RATE'],
                'filter' => 'feedback_loop_id = "999999" AND aggregation_key_type = "ALL_DKIM"',
            ],
        ], $this->requestBody(4)['metricDefinitions']);
    }

    public function test_only_stores_changed_project_values(): void
    {
        $project = ProjectFactory::createOne();
        $projectId = (string) $project->getId();

        ProviderMetricFactory::createOne([
            'project' => $project,
            'metric_date' => new \DateTimeImmutable('2026-06-12'),
            'value' => '0.002',
        ]);

        ProviderMetricFactory::createOne([
            'metric_date' => new \DateTimeImmutable('2026-06-13'),
            'value' => '0.004',
        ]);

        $this->mockHttp([
            new JsonMockResponse([]),
            new JsonMockResponse($this->feedbackLoopIdsResponse(['2026-06-12' => [$projectId]])),
            new JsonMockResponse($this->feedbackLoopRatesResponse([
                '2026-06-12' => [$projectId => 0.002],
                '2026-06-13' => [$projectId => 0.004],
            ])),
        ]);

        $this->runHandler();

        $metrics = $this->em->getRepository(ProviderMetric::class)->findBy([], ['id' => 'ASC']);
        $this->assertCount(3, $metrics);
        $this->assertSame($project->getId(), $metrics[2]->getProject()?->getId());
        $this->assertSame('2026-06-13', $metrics[2]->getMetricDate()->format('Y-m-d'));
    }

    public function test_does_nothing_when_not_configured(): void
    {
        $this->setConfig('googlePostmasterRefreshToken', null);
        $this->mockHttp([]);

        $this->runHandler();

        $this->assertSame([], $this->requests);
        $this->assertCount(0, $this->em->getRepository(ProviderMetric::class)->findAll());
    }

}
