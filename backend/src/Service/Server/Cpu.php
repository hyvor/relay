<?php

namespace App\Service\Server;

class Cpu
{

    public static function getCores(): int
    {
        $cpuCores = shell_exec('nproc');

        if (is_string($cpuCores)) {
            return (int)trim($cpuCores);
        }

        return 1;
    }

}