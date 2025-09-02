<?php

namespace App\Api\Console\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;

class PrometheusFactory
{
    public function createRegistry(): CollectorRegistry
    {
        return new CollectorRegistry(new APC());
    }
}
