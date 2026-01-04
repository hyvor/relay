<?php

namespace App\Tests\Case;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Clock\ClockAwareTrait;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{

    use TestSharedTrait;
    use ClockAwareTrait;

    protected Container $container;
    protected Application $application;
    protected EntityManagerInterface $em;

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
    }

    protected function commandTester(string $name): CommandTester
    {
        $command = $this->application->find($name);
        return new CommandTester($command);
    }

}
