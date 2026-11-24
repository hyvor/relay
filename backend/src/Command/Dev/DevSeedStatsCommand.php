<?php

namespace App\Command\Dev;

use App\Tests\Factory\ProjectFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Seeds the stats rollup tables (stats_project, stats_ip, stats_ip_project, stats_delivery_domain)
 * with realistic data for existing projects and IPs.
 *
 * @codeCoverageIgnore
 */
#[AsCommand(
    name: 'dev:seed:stats',
    description: 'Seeds the stats rollup tables with fake data for development purposes.'
)]
class DevSeedStatsCommand extends Command
{
    /**
     * [domain, share of traffic, bounce multiplier, defer multiplier]
     */
    private const array DOMAINS = [
        ['gmail.com', 0.38, 0.8, 1.2],
        ['outlook.com', 0.14, 1.0, 1.5],
        ['yahoo.com', 0.10, 1.2, 2.0],
        ['icloud.com', 0.07, 0.9, 0.8],
        ['hotmail.com', 0.06, 1.3, 1.4],
        ['proton.me', 0.03, 0.7, 0.6],
        ['aol.com', 0.02, 1.6, 1.8],
        ['example.org', 0.12, 2.0, 0.5],
        ['acme.io', 0.08, 1.1, 0.7],
    ];

    /**
     * Project health profiles:
     * [volume, recipient bounce, infra bounce, unknown bounce, defer, complaint (of accepted), suppressed, failed]
     */
    private const array PROFILES = [
        'healthy' => [1.0, 0.012, 0.001, 0.002, 0.03, 0.0002, 0.01, 0.001],
        'big' => [4.0, 0.018, 0.002, 0.002, 0.04, 0.0003, 0.015, 0.002],
        'sloppy' => [0.6, 0.065, 0.003, 0.004, 0.05, 0.0012, 0.04, 0.002],
        'spammy' => [0.4, 0.045, 0.002, 0.003, 0.06, 0.0045, 0.03, 0.003],
        'tiny' => [0.15, 0.01, 0.001, 0.001, 0.02, 0.0001, 0.005, 0.0],
    ];

    public function __construct(
        private Connection $connection,
        private KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to seed (including today)', '60');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1024M');

        $env = $this->kernel->getEnvironment();
        if ($env !== 'dev' && $env !== 'test') {
            $output->writeln('<error>This command can only be run in the dev and test environments.</error>');
            return Command::FAILURE;
        }

        $days = max(1, (int) $input->getOption('days'));
        mt_srand(42); // deterministic

        /** @var int[] $ipIds */
        $ipIds = array_map('intval', $this->connection->fetchFirstColumn('SELECT id FROM ip_addresses ORDER BY id'));
        if (count($ipIds) === 0) {
            $output->writeln('<error>No IP addresses found. Run dev:seed first.</error>');
            return Command::FAILURE;
        }

        /** @var int[] $projectIds */
        $projectIds = array_map('intval', $this->connection->fetchFirstColumn('SELECT id FROM projects ORDER BY id'));
        $needed = 6;
        $created = 0;
        while (count($projectIds) < $needed) {
            $created++;
            $project = ProjectFactory::createOne(['name' => 'Stats Project ' . $created, 'organization_id' => 1]);
            $projectIds[] = $project->getId();
        }
        $projectIds = array_slice($projectIds, 0, 12);

        // assign profiles round-robin, making sure the interesting ones exist
        $profileNames = array_keys(self::PROFILES);
        $projects = [];
        foreach ($projectIds as $i => $id) {
            $projects[$id] = self::PROFILES[$profileNames[$i % count($profileNames)]];
        }

        // one IP is "bad": infrastructure bounces and deferrals (e.g. blocklisted)
        $badIpId = count($ipIds) > 1 ? $ipIds[count($ipIds) - 1] : null;

        $this->connection->executeStatement('DELETE FROM stats_delivery_domain');
        $this->connection->executeStatement('DELETE FROM stats_ip_project');
        $this->connection->executeStatement('DELETE FROM stats_ip');
        $this->connection->executeStatement('DELETE FROM stats_project');

        $today = new \DateTimeImmutable('today');
        $rows = 0;

        $this->connection->beginTransaction();
        try {
            for ($d = $days - 1; $d >= 0; $d--) {
                $date = $today->modify("-$d days");
                $dateStr = $date->format('Y-m-d');
                $isWeekend = (int) $date->format('N') >= 6;
                // slow growth over time + weekend dip
                $trend = 0.6 + 0.4 * (($days - $d) / $days);
                $dayFactor = $trend * ($isWeekend ? 0.55 : 1.0);
                // a bad incident 3 days ago on the bad IP
                $incident = $d >= 1 && $d <= 3;

                /** @var array<int, array<string, int>> $ipTotals */
                $ipTotals = [];
                /** @var array<string, array<string, int>> $domainTotals keyed "domain" (global) */
                $domainGlobal = [];

                foreach ($projects as $projectId => $p) {
                    [$volume, $bRecipient, $bInfra, $bUnknown, $defer, $complaint, $suppressed, $failed] = $p;

                    $recipients = (int) round(2500 * $volume * $dayFactor * $this->jitter(0.25));
                    if ($recipients < 1) {
                        continue;
                    }
                    $sends = max(1, (int) round($recipients / (1.0 + mt_rand(0, 30) / 100)));
                    $suppressedCount = (int) round($recipients * $suppressed * $this->jitter(0.3));
                    $attempted = $recipients - $suppressedCount;
                    $attempts = (int) round($attempted * (1.05 + $defer));

                    // split attempted across IPs (weighted)
                    $weights = [];
                    foreach ($ipIds as $k => $ipId) {
                        $weights[$ipId] = $k === 0 ? 3 : 1 + ($k % 2);
                    }
                    $weightSum = array_sum($weights);

                    $project = $this->emptyCounts();
                    $project['sends'] = $sends;
                    $project['send_recipients'] = $recipients;
                    $project['send_attempts'] = $attempts;
                    $project['suppressed'] = $suppressedCount;

                    foreach ($ipIds as $ipId) {
                        $ipAttempted = (int) round($attempted * $weights[$ipId] / $weightSum);
                        if ($ipAttempted < 1) {
                            continue;
                        }

                        $infra = $bInfra;
                        $deferRate = $defer;
                        if ($ipId === $badIpId) {
                            $infra = $incident ? 0.12 : $bInfra * 3;
                            $deferRate = $incident ? 0.25 : $defer * 1.5;
                        }

                        $ipc = $this->emptyCounts();
                        $ipc['sends'] = (int) round($sends * $weights[$ipId] / $weightSum);
                        $ipc['send_recipients'] = (int) round($recipients * $weights[$ipId] / $weightSum);
                        $ipc['send_attempts'] = (int) round($attempts * $weights[$ipId] / $weightSum);
                        $ipc['suppressed'] = (int) round($suppressedCount * $weights[$ipId] / $weightSum);
                        $ipc['attempted'] = $ipAttempted;
                        $ipc['bounced_recipient'] = $this->count($ipAttempted, $bRecipient);
                        $ipc['bounced_infrastructure'] = $this->count($ipAttempted, $infra);
                        $ipc['bounced_unknown'] = $this->count($ipAttempted, $bUnknown);
                        $ipc['deferred'] = $this->count($ipAttempted, $deferRate);
                        $ipc['failed'] = $this->count($ipAttempted, $failed);
                        $ipc['accepted'] = max(
                            0,
                            $ipAttempted - $ipc['bounced_recipient'] - $ipc['bounced_infrastructure']
                                - $ipc['bounced_unknown'] - $ipc['deferred'] - $ipc['failed']
                        );
                        $ipc['complained'] = $this->count($ipc['accepted'], $complaint);

                        // ip x project
                        $this->insertIpProject($ipId, $projectId, $dateStr, $ipc);
                        $rows++;

                        // domains for this (project, ip)
                        foreach (self::DOMAINS as [$domain, $share, $bMul, $dMul]) {
                            $sent = $this->count($ipAttempted, $share * $this->jitter(0.2));
                            if ($sent < 1) {
                                continue;
                            }
                            $dc = $this->emptyCounts();
                            $dc['sent'] = $sent;
                            $dc['bounced_recipient'] = $this->count($sent, min(0.5, $bRecipient * $bMul));
                            $dc['bounced_infrastructure'] = $this->count($sent, min(0.5, $infra * $dMul));
                            $dc['bounced_unknown'] = $this->count($sent, $bUnknown);
                            $dc['accepted'] = max(
                                0,
                                $sent - $dc['bounced_recipient'] - $dc['bounced_infrastructure']
                                    - $dc['bounced_unknown']
                            );
                            $dc['complained'] = $this->count($dc['accepted'], $complaint * $bMul);
                            $this->insertDomain($projectId, $ipId, $domain, $dateStr, $dc);
                            $rows++;

                            foreach (['sent', 'accepted', 'bounced_recipient', 'bounced_infrastructure', 'bounced_unknown', 'complained'] as $f) {
                                $domainGlobal[$domain][$f] = ($domainGlobal[$domain][$f] ?? 0) + $dc[$f];
                            }
                        }

                        foreach (['attempted', 'accepted', 'deferred', 'bounced_recipient', 'bounced_infrastructure', 'bounced_unknown', 'failed', 'complained'] as $f) {
                            $project[$f] += $ipc[$f];
                        }

                        foreach ($ipc as $f => $v) {
                            $ipTotals[$ipId][$f] = ($ipTotals[$ipId][$f] ?? 0) + $v;
                        }
                    }

                    $this->insertProject($projectId, $dateStr, $project);
                    $rows++;
                }

                foreach ($ipTotals as $ipId => $totals) {
                    $this->insertIp($ipId, $dateStr, $totals);
                    $rows++;
                }

                // global domain rollups (project_id and ip_address_id NULL)
                foreach ($domainGlobal as $domain => $dc) {
                    $this->insertDomain(null, null, $domain, $dateStr, $dc);
                    $rows++;
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $output->writeln(sprintf(
            '<info>Seeded %d stats rows for %d projects and %d IPs over %d days.</info>',
            $rows,
            count($projects),
            count($ipIds),
            $days
        ));

        return Command::SUCCESS;
    }

    private function jitter(float $amount): float
    {
        return 1.0 + (mt_rand(-1000, 1000) / 1000) * $amount;
    }

    private function count(int $of, float $rate): int
    {
        $expected = $of * $rate * $this->jitter(0.2);
        $floor = (int) floor($expected);
        // probabilistic rounding keeps tiny rates non-zero over time
        return $floor + (mt_rand(0, 1000) / 1000 < ($expected - $floor) ? 1 : 0);
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return [
            'sends' => 0,
            'send_recipients' => 0,
            'send_attempts' => 0,
            'attempted' => 0,
            'sent' => 0,
            'accepted' => 0,
            'deferred' => 0,
            'bounced_recipient' => 0,
            'bounced_infrastructure' => 0,
            'bounced_unknown' => 0,
            'complained' => 0,
            'suppressed' => 0,
            'failed' => 0,
        ];
    }

    /**
     * @param array<string, int> $c
     * @return array<string, float|null>
     */
    private function rates(array $c, bool $full): array
    {
        $att = $c['attempted'] ?: ($c['sent'] ?? 0);
        $r = fn(int $n, int $d, int $p) => $d > 0 ? round($n / $d, $p) : null;

        $rates = [
            'bounced_recipient_rate' => $r($c['bounced_recipient'], $att, 4),
            'bounced_infrastructure_rate' => $r($c['bounced_infrastructure'], $att, 4),
            'bounced_unknown_rate' => $r($c['bounced_unknown'], $att, 4),
            'complained_rate' => $r($c['complained'], $c['accepted'], 6),
        ];

        if ($full) {
            $rates += [
                'accepted_rate' => $r($c['accepted'], $att, 4),
                'deferred_rate' => $r($c['deferred'], $att, 4),
                'suppressed_rate' => $r($c['suppressed'], $c['send_recipients'], 4),
                'failed_rate' => $r($c['failed'], $att, 4),
            ];
        }

        return $rates;
    }

    /**
     * @param array<string, int> $c
     * @param array<string, int|float|string|null> $keys
     * @param string[] $countFields
     */
    private function insert(string $table, array $keys, array $c, array $countFields, bool $fullRates, bool $bounceRates = true): void
    {
        $data = $keys;
        foreach ($countFields as $f) {
            $data[$f] = $c[$f];
        }
        $rates = $this->rates($c, $fullRates);
        if (!$bounceRates) {
            $rates = ['complained_rate' => $rates['complained_rate']];
        }
        $data += $rates;

        $this->connection->insert($table, $data);
    }

    /** @param array<string, int> $c */
    private function insertProject(int $projectId, string $date, array $c): void
    {
        $this->insert(
            'stats_project',
            ['project_id' => $projectId, 'stat_date' => $date],
            $c,
            [
                'sends',
                'send_recipients',
                'send_attempts',
                'attempted',
                'accepted',
                'deferred',
                'bounced_recipient',
                'bounced_infrastructure',
                'bounced_unknown',
                'complained',
                'suppressed',
                'failed'
            ],
            true,
        );
    }

    /** @param array<string, int> $c */
    private function insertIp(int $ipId, string $date, array $c): void
    {
        $this->insert(
            'stats_ip',
            ['ip_address_id' => $ipId, 'stat_date' => $date],
            $c,
            [
                'sends',
                'send_recipients',
                'send_attempts',
                'accepted',
                'deferred',
                'bounced_recipient',
                'bounced_infrastructure',
                'bounced_unknown',
                'complained',
                'suppressed',
                'failed'
            ],
            true,
        );
    }

    /** @param array<string, int> $c */
    private function insertIpProject(int $ipId, int $projectId, string $date, array $c): void
    {
        $c['sent'] = $c['attempted'];
        $this->insert(
            'stats_ip_project',
            ['ip_address_id' => $ipId, 'project_id' => $projectId, 'stat_date' => $date],
            $c,
            ['sent', 'accepted', 'bounced_recipient', 'bounced_infrastructure', 'bounced_unknown', 'complained'],
            false,
        );
    }

    /** @param array<string, int> $c */
    private function insertDomain(?int $projectId, ?int $ipId, string $domain, string $date, array $c): void
    {
        $c += $this->emptyCounts();
        $this->insert(
            'stats_delivery_domain',
            ['project_id' => $projectId, 'ip_address_id' => $ipId, 'recipient_domain' => $domain, 'stat_date' => $date],
            $c,
            ['sent', 'accepted', 'bounced_recipient', 'bounced_infrastructure', 'bounced_unknown', 'complained'],
            false,
            false,
        );
    }
}
