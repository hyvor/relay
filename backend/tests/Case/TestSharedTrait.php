<?php

namespace App\Tests\Case;

use App\Service\App\Config;
use Monolog\Handler\TestHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
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

    public function getTestLogger(): TestHandler
    {
        $logger = $this->container->get('monolog.handler.test');
        $this->assertInstanceOf(TestHandler::class, $logger);
        return $logger;
    }

    protected function getMessageBus(): MessageBusInterface
    {
        /** @var MessageBusInterface $bus */
        $bus = $this->container->get('messenger.default_bus');
        return $bus;
    }

    /**
     * @template T of object
     * @param class-string<T> $serviceId
     * @return T
     */
    public function getService(string $serviceId): mixed
    {
        /** @var T $service */
        $service = $this->container->get($serviceId);
        return $service;
    }

    public function getEd(): EventDispatcherInterface
    {
        return $this->getService(EventDispatcherInterface::class);
    }

}
