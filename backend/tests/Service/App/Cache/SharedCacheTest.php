<?php

namespace App\Tests\Service\App\Cache;

use App\Service\App\Cache\SharedCache;
use App\Tests\Case\KernelTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SharedCache::class)]
class SharedCacheTest extends KernelTestCase
{
    public function test_php_writes_are_readable_by_go(): void
    {
        $cache = $this->sharedCache();

        self::assertTrue($cache->set('mx:example.com', ['host' => 'mx.example.com'], 3600));

        /** @var Connection $connection */
        $connection = $this->em->getConnection();
        $row = $connection->fetchAssociative(
            'SELECT item_id, item_data, item_lifetime FROM cache_items WHERE item_id = ?',
            ['shared-v1:h.e4b96a16d2ff7506f1554ec72f5aeb3e347284fe302dd2dd90af34c5b9276e77'],
        );

        self::assertIsArray($row);
        self::assertSame(
            'shared-v1:h.e4b96a16d2ff7506f1554ec72f5aeb3e347284fe302dd2dd90af34c5b9276e77',
            $row['item_id'],
        );
        self::assertIsResource($row['item_data']);
        self::assertSame('{"host":"mx.example.com"}', stream_get_contents($row['item_data']));
        self::assertSame(3600, $row['item_lifetime']);
    }

    public function test_php_reads_go_cache_entries(): void
    {
        /** @var Connection $connection */
        $connection = $this->em->getConnection();
        $connection->delete('cache_items', [
            'item_id' => 'shared-v1:h.ec06a31deb92499b60429301ecc5cac14f660cb4177110fbaf79f298122ee038',
        ]);
        $connection->insert('cache_items', [
            'item_id' => 'shared-v1:h.ec06a31deb92499b60429301ecc5cac14f660cb4177110fbaf79f298122ee038',
            'item_data' => 'true',
            'item_lifetime' => 3600,
            'item_time' => time(),
        ]);

        $cache = $this->sharedCache();

        self::assertTrue($cache->get('mta_sts:example.com'));
    }

    public function test_nonpositive_ttl_deletes_an_entry(): void
    {
        $cache = $this->sharedCache();

        self::assertTrue($cache->set('mx:example.com', ['host' => 'mx.example.com'], 3600));
        self::assertTrue($cache->set('mx:example.com', ['host' => 'mx.example.com'], 0));
        self::assertNull($cache->get('mx:example.com'));
    }

    public function test_empty_keys_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sharedCache()->get('');
    }

    private function sharedCache(): SharedCache
    {
        /** @var SharedCache $cache */
        $cache = $this->container->get(SharedCache::class);

        return $cache;
    }
}
