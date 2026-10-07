<?php

namespace App\Service\ProviderMetric\MessageHandler;

use App\Entity\Project;
use App\Entity\ProviderMetric;
use App\Entity\Type\ProviderMetricSource;
use App\Service\App\Config;
use App\Service\ProviderMetric\GooglePostmasterClient;
use App\Service\ProviderMetric\Message\FetchGooglePostmasterMetricsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FetchGooglePostmasterMetricsMessageHandler
{
    use ClockAwareTrait;

    private const int LOOKBACK_DAYS = 7;

    public function __construct(
        private GooglePostmasterClient $client,
        private Config $config,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(FetchGooglePostmasterMetricsMessage $message): void
    {
        if (!$this->client->isConfigured()) {
            return;
        }

        $domain = $this->config->getInstanceDomain();
        $today = $this->now()->setTime(0, 0);
        $from = $today->modify('-' . self::LOOKBACK_DAYS . ' days');

        foreach ($this->client->getSpamRates($domain, $from, $today) as $date => $spamRate) {
            $this->storeIfChanged(new \DateTimeImmutable($date), null, $spamRate);
        }

        $this->em->flush();

        foreach ($this->client->getFeedbackLoopSpamRates($domain, $from, $today) as $row) {
            $project = ctype_digit($row['id']) ? $this->em->find(Project::class, (int) $row['id']) : null;

            if ($project === null) {
                continue;
            }

            $this->storeIfChanged(new \DateTimeImmutable($row['date']), $project, $row['rate']);
        }

        $this->em->flush();
    }

    private function storeIfChanged(\DateTimeImmutable $metricDate, ?Project $project, float $spamRate): void
    {
        $latest = $this->getLatestValue($metricDate, $project);
        if ($latest !== null && abs((float) $latest - $spamRate) < 1e-9) {
            return;
        }

        $metric = new ProviderMetric()
            ->setCreatedAt($this->now())
            ->setSource(ProviderMetricSource::GOOGLE)
            ->setProject($project)
            ->setMetricDate($metricDate)
            ->setValue(number_format($spamRate, 10, '.', ''));

        $this->em->persist($metric);
    }

    private function getLatestValue(\DateTimeImmutable $metricDate, ?Project $project): ?string
    {
        /** @var string|false $value */
        $value = $this->em->getConnection()->fetchOne(
            <<<SQL
            SELECT value FROM provider_metrics
            WHERE source = :source
            AND project_id IS NOT DISTINCT FROM :projectId
            AND ip_address_id IS NULL
            AND metric_date = :date
            ORDER BY id DESC
            LIMIT 1
            SQL,
            [
                'source' => ProviderMetricSource::GOOGLE->value,
                'projectId' => $project?->getId(),
                'date' => $metricDate->format('Y-m-d'),
            ]
        );

        return $value === false ? null : $value;
    }
}
