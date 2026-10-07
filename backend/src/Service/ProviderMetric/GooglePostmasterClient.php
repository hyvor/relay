<?php

namespace App\Service\ProviderMetric;

use App\Service\App\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * https://developers.google.com/workspace/gmail/postmaster/reference/rest/v2
 *
 * @phpstan-type DomainStat array{
 *     metric?: string,
 *     date?: array{year: int, month: int, day: int},
 *     value?: array{floatValue?: float|int, stringList?: array{values?: list<string>}}
 * }
 */
class GooglePostmasterClient
{

    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const string API_URL = 'https://gmailpostmastertools.googleapis.com/v2';

    private const string AGGREGATION_KEY_FILTER = 'aggregation_key_type = "ALL_DKIM"';
    private const string FEEDBACK_LOOP_METRIC_PREFIX = 'fbl_';

    public function __construct(
        private HttpClientInterface $httpClient,
        private Config $config,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->config->getGooglePostmasterClientId() !== null
            && $this->config->getGooglePostmasterClientSecret() !== null
            && $this->config->getGooglePostmasterRefreshToken() !== null;
    }

    /**
     * @return array<string, float> spam rate by Y-m-d
     */
    public function getSpamRates(string $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $stats = $this->query(
            $this->getAccessToken(),
            $domain,
            [['name' => 'spam_rate', 'baseMetric' => ['standardMetric' => 'SPAM_RATE']]],
            $from,
            $to
        );

        $rates = [];

        foreach ($stats as $stat) {
            $date = $this->formatDate($stat);
            $value = $stat['value']['floatValue'] ?? null;

            if ($date !== null && $value !== null) {
                $rates[$date] = (float) $value;
            }
        }

        return $rates;
    }

    /**
     * @return list<array{date: string, id: string, rate: float}>
     */
    public function getFeedbackLoopSpamRates(string $domain, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $accessToken = $this->getAccessToken();

        $idStats = $this->query(
            $accessToken,
            $domain,
            [[
                'name' => 'feedback_loop_id',
                'baseMetric' => ['standardMetric' => 'FEEDBACK_LOOP_ID'],
                'filter' => self::AGGREGATION_KEY_FILTER,
            ]],
            $from,
            $to
        );

        $ids = [];

        foreach ($idStats as $stat) {
            array_push($ids, ...($stat['value']['stringList']['values'] ?? []));
        }

        if ($ids === []) {
            return [];
        }

        $metricDefinitions = [];

        foreach (array_unique($ids) as $id) {
            $metricDefinitions[] = [
                'name' => self::FEEDBACK_LOOP_METRIC_PREFIX . $id,
                'baseMetric' => ['standardMetric' => 'FEEDBACK_LOOP_SPAM_RATE'],
                'filter' => 'feedback_loop_id = "' . $id . '" AND ' . self::AGGREGATION_KEY_FILTER,
            ];
        }

        $rates = [];

        foreach ($this->query($accessToken, $domain, $metricDefinitions, $from, $to) as $stat) {
            $date = $this->formatDate($stat);
            $metric = $stat['metric'] ?? null;
            $value = $stat['value']['floatValue'] ?? null;

            if ($date !== null && $metric !== null && $value !== null) {
                $rates[] = [
                    'date' => $date,
                    'id' => substr($metric, strlen(self::FEEDBACK_LOOP_METRIC_PREFIX)),
                    'rate' => (float) $value,
                ];
            }
        }

        return $rates;
    }

    /**
     * @param list<array<string, mixed>> $metricDefinitions
     * @return list<DomainStat>
     */
    private function query(
        string $accessToken,
        string $domain,
        array $metricDefinitions,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        $stats = [];
        $pageToken = null;

        do {
            $body = [
                'parent' => 'domains/' . $domain,
                'metricDefinitions' => $metricDefinitions,
                'timeQuery' => [
                    'dateRanges' => [
                        'dateRanges' => [
                            ['start' => $this->toDate($from), 'end' => $this->toDate($to)],
                        ],
                    ],
                ],
                'aggregationGranularity' => 'DAILY',
                'pageSize' => 200,
            ];

            if ($pageToken !== null) {
                $body['pageToken'] = $pageToken;
            }

            /** @var array{domainStats?: list<DomainStat>, nextPageToken?: string} $data */
            $data = $this->httpClient->request(
                'POST',
                self::API_URL . '/domains/' . rawurlencode($domain) . '/domainStats:query',
                [
                    'auth_bearer' => $accessToken,
                    'json' => $body,
                ]
            )->toArray();

            array_push($stats, ...($data['domainStats'] ?? []));

            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken !== null && $pageToken !== '');

        return $stats;
    }

    private function getAccessToken(): string
    {
        /** @var array{access_token: string} $data */
        $data = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->config->getGooglePostmasterClientId(),
                'client_secret' => $this->config->getGooglePostmasterClientSecret(),
                'refresh_token' => $this->config->getGooglePostmasterRefreshToken(),
                'grant_type' => 'refresh_token',
            ],
        ])->toArray();

        return $data['access_token'];
    }

    /**
     * @param DomainStat $stat
     */
    private function formatDate(array $stat): ?string
    {
        $date = $stat['date'] ?? null;

        if ($date === null) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $date['year'], $date['month'], $date['day']);
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    private function toDate(\DateTimeImmutable $date): array
    {
        return [
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'day' => (int) $date->format('j'),
        ];
    }

}
