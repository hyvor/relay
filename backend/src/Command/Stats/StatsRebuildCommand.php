<?php

namespace App\Command\Stats;

use App\Service\Stats\MessageHandler\UpdateStatsMessageHandler;
use App\Service\Stats\StatsService;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand('stats:rebuild', description: 'Re-count the stats of the given dates from scratch')]
class StatsRebuildCommand extends Command
{
    use ClockAwareTrait;

    public function __construct(
        private readonly StatsService $statsService,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('from', InputArgument::REQUIRED, 'First date to re-count (Y-m-d)')
            ->addArgument('to', InputArgument::OPTIONAL, 'Last date to re-count (Y-m-d). Defaults to "from"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $from */
        $from = $input->getArgument('from');
        /** @var string $to */
        $to = $input->getArgument('to') ?? $from;

        $fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);

        if ($fromDate === false || $toDate === false || $fromDate > $toDate) {
            $output->writeln('<error>Invalid dates. Use Y-m-d, and "from" must not be after "to".</error>');
            return Command::INVALID;
        }

        // sends are deleted after the retention period, so older days can not be re-counted
        $minDate = $this->now()->modify('-' . UpdateStatsMessageHandler::MAX_REBUILD_AGE_DAYS . ' days')->format('Y-m-d');
        if ($fromDate->format('Y-m-d') < $minDate) {
            $output->writeln("<error>Dates older than $minDate can not be re-counted (raw data may be deleted).</error>");
            return Command::INVALID;
        }

        // do not run at the same time as the scheduled update
        $lock = $this->lockFactory->createLock(UpdateStatsMessageHandler::LOCK_NAME);
        $lock->acquire(true);

        try {
            for ($date = $fromDate; $date <= $toDate; $date = $date->modify('+1 day')) {
                $output->write('Re-counting ' . $date->format('Y-m-d') . ' ... ');
                $startedAt = hrtime(true);
                $this->statsService->rebuildDate($date->format('Y-m-d'));
                $output->writeln(sprintf('done in %.2fs', (hrtime(true) - $startedAt) / 1e9));
            }
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
