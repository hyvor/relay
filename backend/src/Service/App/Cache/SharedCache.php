<?php

namespace App\Service\App\Cache;

use Psr\Cache\CacheItemPoolInterface;

final readonly class SharedCache
{
    public const NAMESPACE = 'shared-v1';

    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    public function get(string $key): mixed
    {
        return $this->pool->getItem(self::encodeKey($key))->get();
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        if ($ttl <= 0) {
            return $this->delete($key);
        }

        $item = $this->pool->getItem(self::encodeKey($key));
        $item->set($value);
        $item->expiresAfter($ttl);

        return $this->pool->save($item);
    }

    public function delete(string $key): bool
    {
        return $this->pool->deleteItem(self::encodeKey($key));
    }

    public static function encodeKey(string $key): string
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Cache key cannot be empty');
        }

        return 'h.'.hash('sha256', $key);
    }
}
