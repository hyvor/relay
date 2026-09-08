package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"sync"
	"time"

	"github.com/jellydator/ttlcache/v3"
	"golang.org/x/sync/singleflight"
)

const (
	sharedCacheMemoryCapacity = 10_000
	sharedCacheMemoryMaxCost  = 32 * 1024 * 1024
	sharedCacheMaxValueSize   = 1024 * 1024
)

var ErrCacheValueTooLarge = errors.New("cache value is too large")

type sharedCacheMemoryValue []byte

// SharedCache stores JSON values in a bounded local cache backed by Symfony's
// Doctrine DBAL cache table.
type SharedCache struct {
	dbMu   sync.RWMutex
	db     *sql.DB
	memory *ttlcache.Cache[string, sharedCacheMemoryValue]
	loads  singleflight.Group
	now    func() time.Time
	cancel context.CancelFunc
	wg     sync.WaitGroup
}

var processSharedCache struct {
	sync.Mutex
	value *SharedCache
}

func getProcessSharedCache() *SharedCache {
	processSharedCache.Lock()
	defer processSharedCache.Unlock()
	if processSharedCache.value == nil {
		processSharedCache.value = NewSharedCache(nil)
	}
	return processSharedCache.value
}

// ConfigureProcessSharedCache attaches the process cache to a database. The
// first worker connection is enough because *sql.DB is safe for concurrent use.
func ConfigureProcessSharedCache(db *sql.DB) {
	processSharedCache.Lock()
	defer processSharedCache.Unlock()
	if processSharedCache.value == nil {
		processSharedCache.value = NewSharedCache(db)
		return
	}
	processSharedCache.value.setDatabase(db)
}

func (c *SharedCache) setDatabase(db *sql.DB) {
	c.dbMu.Lock()
	defer c.dbMu.Unlock()
	if c.db == nil {
		c.db = db
	}
}

func (c *SharedCache) database() *sql.DB {
	c.dbMu.RLock()
	defer c.dbMu.RUnlock()
	return c.db
}

func NewSharedCache(db *sql.DB) *SharedCache {
	memory := ttlcache.New(
		ttlcache.WithCapacity[string, sharedCacheMemoryValue](sharedCacheMemoryCapacity),
		ttlcache.WithMaxCost[string, sharedCacheMemoryValue](sharedCacheMemoryMaxCost, func(item ttlcache.CostItem[string, sharedCacheMemoryValue]) uint64 {
			return uint64(len(item.Key) + len(item.Value) + 64)
		}),
		ttlcache.WithDisableTouchOnHit[string, sharedCacheMemoryValue](),
	)

	cache := &SharedCache{
		db:     db,
		memory: memory,
		now:    time.Now,
	}
	ctx, cancel := context.WithCancel(context.Background())
	cache.cancel = cancel
	cache.wg.Add(1)
	go func() {
		defer cache.wg.Done()
		ticker := time.NewTicker(time.Minute)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				memory.DeleteExpired()
			}
		}
	}()

	return cache
}

func (c *SharedCache) Close() {
	c.cancel()
	c.wg.Wait()
}

func (c *SharedCache) Get(ctx context.Context, key string, destination any) (bool, error) {
	if item := c.memory.Get(key); item != nil {
		if err := json.Unmarshal(item.Value(), destination); err != nil {
			c.memory.Delete(key)
			return false, fmt.Errorf("decode memory cache value for %q: %w", key, err)
		}
		return true, nil
	}

	if c.database() == nil {
		return false, nil
	}

	loaded, err, _ := c.loads.Do(key, func() (any, error) {
		return c.loadFromDatabase(ctx, key)
	})
	if err != nil {
		return false, err
	}

	entry := loaded.(sharedCacheDatabaseEntry)
	if !entry.found {
		return false, nil
	}
	if err := json.Unmarshal(entry.value, destination); err != nil {
		return false, fmt.Errorf("decode database cache value for %q: %w", key, err)
	}

	return true, nil
}

func (c *SharedCache) Set(ctx context.Context, key string, value any, ttl time.Duration) error {
	if ttl <= 0 {
		return c.Delete(ctx, key)
	}

	encoded, err := json.Marshal(value)
	if err != nil {
		return fmt.Errorf("encode cache value for %q: %w", key, err)
	}
	if len(encoded) > sharedCacheMaxValueSize {
		return fmt.Errorf("%w: %d bytes", ErrCacheValueTooLarge, len(encoded))
	}

	c.memory.Set(key, encoded, ttl)
	if c.database() == nil {
		return nil
	}

	if err := c.storeInDatabase(ctx, key, encoded, ttl); err != nil {
		return fmt.Errorf("store database cache value for %q: %w", key, err)
	}
	return nil
}

func (c *SharedCache) Delete(ctx context.Context, key string) error {
	c.memory.Delete(key)
	if c.database() == nil {
		return nil
	}

	if err := c.deleteFromDatabase(ctx, key); err != nil {
		return fmt.Errorf("delete database cache value for %q: %w", key, err)
	}
	return nil
}
