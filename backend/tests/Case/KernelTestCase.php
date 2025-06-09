<?php

namespace App\Tests\Case;

use App\Config;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{

    protected Container $container;
    protected Application $application;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->container = static::getContainer();
        $this->application = new Application(self::$kernel);

        /** @var EntityManagerInterface $em */
        $em = $this->container->get(EntityManagerInterface::class);
        $this->em = $em;
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
        $property->setAccessible(true);
        $property->setValue($config, $value);
    }

}