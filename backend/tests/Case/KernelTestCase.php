<?php

namespace App\Tests\Case;

use App\Config;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{

    use InteractsWithMessenger;

    protected Container $container;
    protected Application $application;
    protected EntityManagerInterface $em;
    protected EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->container = static::getContainer();
        
        assert(self::$kernel !== null, 'Kernel should be booted');
        $this->application = new Application(self::$kernel);

        /** @var EntityManagerInterface $em */
        $em = $this->container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function commandTester(string $name): CommandTester
    {
        $command = $this->application->find($name);
        return new CommandTester($command);
    }
  
    protected function setConfig(string $key, mixed $value): void
    {
        $config = $this->getContainer()->get(Config::class);
        assert(property_exists($config, $key));
        $reflection = new \ReflectionObject($config);
        $property = $reflection->getProperty($key);
        $property->setValue($config, $value);
    }

}
