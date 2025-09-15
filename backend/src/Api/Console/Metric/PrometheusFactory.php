<?php

namespace App\Api\Console\Metric;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;

class PrometheusFactory
{
    public function __construct(
        private Adapter $adapter,
    ) {
    }

    public function createRegistry(): CollectorRegistry
    {
        return new CollectorRegistry($this->adapter);
    }
}
