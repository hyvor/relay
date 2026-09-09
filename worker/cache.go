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

type sharedCacheLoad struct {
	entry      sharedCacheDatabaseEntry
	generation uint64
}

// SharedCache stores JSON values in a bounded local cache backed by Symfony's
// Doctrine DBAL cache table.
type SharedCache struct {
	dbMu         sync.RWMutex
	db           *sql.DB
	memory       *ttlcache.Cache[string, sharedCacheMemoryValue]
	loads        singleflight.Group
	now          func() time.Time
	cancel       context.CancelFunc
	wg           sync.WaitGroup
	writeMu      sync.Mutex
	generationMu sync.Mutex
	generation   uint64
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

// ConfigureProcessSharedCache attaches the process cache to a process-owned
// database handle. The caller must not close the handle when this returns true.
func ConfigureProcessSharedCache(db *sql.DB) bool {
	processSharedCache.Lock()
	defer processSharedCache.Unlock()
	if processSharedCache.value == nil {
		processSharedCache.value = NewSharedCache(db)
		return true
	}
	if processSharedCache.value.database() == nil {
		processSharedCache.value.attachDatabase(db)
		return true
	}
	return processSharedCache.value.database() == db
}

func (c *SharedCache) attachDatabase(db *sql.DB) {
	c.dbMu.Lock()
	defer c.dbMu.Unlock()
	if c.db == nil {
		c.db = db
	}
}

func CloseProcessSharedCache() {
	processSharedCache.Lock()
	cache := processSharedCache.value
	processSharedCache.value = nil
	processSharedCache.Unlock()
	if cache == nil {
		return
	}
	cache.Close()
	if db := cache.database(); db != nil {
		_ = db.Close()
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
	if _, err := sharedCacheItemID(key); err != nil {
		return false, err
	}

	for {
		c.writeMu.Lock()
		item := c.memory.Get(key)
		if item != nil {
			if err := json.Unmarshal(item.Value(), destination); err != nil {
				c.memory.Delete(key)
				c.writeMu.Unlock()
				return false, fmt.Errorf("decode memory cache value for %q: %w", key, err)
			}
			c.writeMu.Unlock()
			return true, nil
		}
		generation := c.currentGeneration()
		c.writeMu.Unlock()

		if c.database() == nil {
			return false, nil
		}

		loadCtx := context.WithoutCancel(ctx)
		result := c.loads.DoChan(fmt.Sprintf("%d:%s", generation, key), func() (any, error) {
			entry, err := c.loadFromDatabase(loadCtx, key)
			return sharedCacheLoad{entry: entry, generation: generation}, err
		})
		var load sharedCacheLoad
		var err error
		select {
		case <-ctx.Done():
			return false, ctx.Err()
		case result := <-result:
			load = result.Val.(sharedCacheLoad)
			err = result.Err
		}

		c.writeMu.Lock()
		if c.currentGeneration() != load.generation {
			c.writeMu.Unlock()
			continue
		}
		if err != nil {
			c.writeMu.Unlock()
			return false, err
		}
		if !load.entry.found {
			c.writeMu.Unlock()
			return false, nil
		}
		remaining := load.entry.expiresAt.Sub(c.now())
		if remaining <= 0 {
			c.writeMu.Unlock()
			return false, nil
		}
		if err := json.Unmarshal(load.entry.value, destination); err != nil {
			c.writeMu.Unlock()
			return false, fmt.Errorf("decode database cache value for %q: %w", key, err)
		}
		c.memory.Set(key, load.entry.value, remaining)
		c.writeMu.Unlock()

		return true, nil
	}
}

func (c *SharedCache) Set(ctx context.Context, key string, value any, ttl time.Duration) error {
	if _, err := sharedCacheItemID(key); err != nil {
		return err
	}
	if ttl <= 0 {
		return c.Delete(ctx, key)
	}
	c.writeMu.Lock()
	defer c.writeMu.Unlock()
	c.bumpGeneration()

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
	c.bumpGeneration()
	return nil
}

func (c *SharedCache) Delete(ctx context.Context, key string) error {
	if _, err := sharedCacheItemID(key); err != nil {
		return err
	}
	c.writeMu.Lock()
	defer c.writeMu.Unlock()
	c.bumpGeneration()
	c.memory.Delete(key)
	if c.database() == nil {
		return nil
	}

	if err := c.deleteFromDatabase(ctx, key); err != nil {
		return fmt.Errorf("delete database cache value for %q: %w", key, err)
	}
	c.bumpGeneration()
	return nil
}

func (c *SharedCache) currentGeneration() uint64 {
	c.generationMu.Lock()
	defer c.generationMu.Unlock()
	return c.generation
}

func (c *SharedCache) bumpGeneration() {
	c.generationMu.Lock()
	c.generation++
	c.generationMu.Unlock()
}
