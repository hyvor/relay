<?php

namespace App\Tests\Service\App\Cache;

use App\Service\App\Cache\SharedCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(SharedCache::class)]
class SharedCacheTest extends TestCase
{
    public function test_round_trip(): void
    {
        $cache = new SharedCache(new ArrayAdapter());

        self::assertTrue($cache->set('mx:example.com', ['host' => 'mx.example.com'], 3600));
        self::assertSame(['host' => 'mx.example.com'], $cache->get('mx:example.com'));
        self::assertTrue($cache->delete('mx:example.com'));
        self::assertNull($cache->get('mx:example.com'));
    }

    public function test_key_encoding_matches_go(): void
    {
        self::assertSame('h.'.hash('sha256', 'mx:example.com'), SharedCache::encodeKey('mx:example.com'));
        self::assertStringStartsWith('h.', SharedCache::encodeKey(str_repeat('a', 300)));
        self::assertLessThanOrEqual(239, strlen(SharedCache::encodeKey(str_repeat('a', 300))));
    }

    public function test_empty_keys_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SharedCache::encodeKey('');
    }
}
