<?php

namespace App\Tests\Case;

use App\Service\App\Config;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

trait TestSharedTrait
{

    use InteractsWithMessenger;
    use Factories;

    protected function setConfig(string $key, mixed $value): void
    {
        $config = $this->container->get(Config::class);
        assert(property_exists($config, $key));
        $reflection = new \ReflectionObject($config);
        $property = $reflection->getProperty($key);
        $property->setValue($config, $value);
    }

}