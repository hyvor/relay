<?php

namespace App\Api\Console\Metrics;

use App\Service\App\Config;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;

class PrometheusFactory
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function createRegistry(): CollectorRegistry
    {
        if ($this->config->getEnv() !== 'prod') {
            return new CollectorRegistry(new InMemory());
        }

        return new CollectorRegistry(new APC());
    }
}
