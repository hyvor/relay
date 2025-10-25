<?php

namespace App\Service\App\Process;

use Symfony\Component\Process\Process;

class ProcessFactory
{

    /**
     * @param string[] $command
     * @param array<string, string>|null $env
     */
    public function create(array $command, ?array $env = null, ?float $timeout = 60): Process
    {
        return new Process($command, env: $env, timeout: $timeout);
    }

}