<?php

namespace App\Tests\Command\Management;

use App\Command\Management\ManagementInitCommand;
use App\Service\Management\ManagementService;
use App\Service\Server\ServerService;
use App\Tests\Case\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ManagementInitCommand::class)]
#[CoversClass(ManagementService::class)]
#[CoversClass(ServerService::class)]
class ManagementInitCommandTest extends KernelTestCase
{

    public function test_creates_server(): void
    {

        $command = $this->commandTester('management:init');
        $command->execute([]);
        $command->assertCommandIsSuccessful();

    }

}