<?php

declare(strict_types=1);

namespace App\Service\MxServer;

use App\Service\App\Config;

class MxServer
{
    public function __construct(private Config $config)
    {
    }

    public function getMxHostname(): string
    {
        return 'mx.' . $this->config->getInstanceDomain();
    }

}
