<?php

namespace App\Tests\Service\App\Process;

use App\Service\App\Process\ProcessFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessFactory::class)]
class ProcessFactoryTest extends TestCase
{

    public function test_create_process(): void
    {
        $factory = new ProcessFactory();
        $process = $factory->create(['echo', 'Hello, World!'], env: ['FOO' => 'bar'], timeout: 30.0);
        $this->assertSame("'echo' 'Hello, World!'", $process->getCommandLine());
        $this->assertSame(['FOO' => 'bar'], $process->getEnv());
        $this->assertSame(30.0, $process->getTimeout());
    }

}