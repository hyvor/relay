<?php

namespace App\Service\App\RateLimit;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimiterProvider
{

    public function __construct(
        private LockFactory $lockFactory,
        private CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    /**
     * @param array<mixed> $config
     */
    public function factory(array $config): RateLimiterFactory
    {
        $storage = new CacheStorage($this->cacheItemPool);
        return new RateLimiterFactory($config, $storage, $this->lockFactory);
    }

    /**
     * @param array<mixed> $config
     */
    public function rateLimiter(array $config, string $key): LimiterInterface
    {
        $rateLimiter = $this->factory($config);
        return $rateLimiter->create($key);
    }

}