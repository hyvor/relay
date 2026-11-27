<?php

namespace App\Service\Cloud\MeteredUsage\Command;

use App\Service\Cloud\MeteredUsage\MeteredUsageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('cloud:report-metered-usage', description: 'Report metered usage of a given day')]
class ReportMeteredUsageCommand extends Command
{
    public function __construct(
        private MeteredUsageService $meteredUsageService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Day to report (Y-m-d)');
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Replace the usage already recorded in core for the day (no effect if already invoiced)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dateInput = $input->getOption('date');

        if (!is_string($dateInput)) {
            $output->writeln('<error>--date is required (Y-m-d)</error>');
            return Command::INVALID;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);

        if ($date === false || $date->format('Y-m-d') !== $dateInput) {
            $output->writeln('<error>Invalid --date. Expected format: Y-m-d</error>');
            return Command::INVALID;
        }

        $force = (bool) $input->getOption('force');
        $count = $this->meteredUsageService->reportDailyUsage($date, $force);

        $output->writeln(
            "Metered usage for {$date->format('Y-m-d')} queued for reporting ({$count} organizations)" .
            ($force ? ' (forced)' : '')
        );

        return Command::SUCCESS;
    }
}
