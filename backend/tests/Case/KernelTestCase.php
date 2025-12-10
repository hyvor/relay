<?php

namespace App\Tests\Case;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Lock\LockFactory;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{

    use TestSharedTrait;

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

        $this->resetInMemoryLockStore();
    }

    /**
     * Clears the lock storage in Symfony's InMemoryStore service.
     * The container is reused between tests, so locks can leak.
     */
    private function resetInMemoryLockStore(): void
    {
        $lockFactory = $this->container->get(LockFactory::class);
        $factoryReflection = new \ReflectionClass($lockFactory);
        $storeProperty = $factoryReflection->getProperty('store');
        $store = $storeProperty->getValue($lockFactory);

        $storeReflection = new \ReflectionClass($store);
        if ($storeReflection->hasProperty('locks')) {
            $locksProperty = $storeReflection->getProperty('locks');
            $locksProperty->setValue($store, []);
        }
        if ($storeReflection->hasProperty('readLocks')) {
            $readLocksProperty = $storeReflection->getProperty('readLocks');
            $readLocksProperty->setValue($store, []);
        }
    }

    protected function commandTester(string $name): CommandTester
    {
        $command = $this->application->find($name);
        return new CommandTester($command);
    }

}
