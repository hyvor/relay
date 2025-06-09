<?php

namespace App\Command\Management;

use App\Service\Management\ManagementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('management:init', description: 'Initialize the current system in the database')]
class ManagementInitCommand extends Command
{

    public function __construct(private readonly ManagementService $managementService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->managementService->setOutput($output);
        $this->managementService->initialize();

        return Command::SUCCESS;
    }

}