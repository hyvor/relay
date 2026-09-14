package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
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

type SharedCache struct {
	db     *sql.DB
	memory *ttlcache.Cache[string, sharedCacheMemoryValue]
	loads  singleflight.Group
	now    func() time.Time
	loader func(context.Context, string) (sharedCacheDatabaseEntry, error)
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

	return cache
}

func (c *SharedCache) Get(ctx context.Context, key string, destination any) (bool, error) {
	if _, err := sharedCacheItemID(key); err != nil {
		return false, err
	}

	if item := c.memory.Get(key); item != nil {
		if err := json.Unmarshal(item.Value(), destination); err != nil {
			c.memory.Delete(key)
			return false, fmt.Errorf("decode memory cache value for %q: %w", key, err)
		}
		return true, nil
	}

	if !c.canLoad() {
		return false, nil
	}

	loadCtx := context.WithoutCancel(ctx)
	result := c.loads.DoChan(key, func() (any, error) {
		return c.load(loadCtx, key)
	})

	var entry sharedCacheDatabaseEntry
	var err error

	select {
	case <-ctx.Done():
		return false, ctx.Err()
	case shared := <-result:
		entry, _ = shared.Val.(sharedCacheDatabaseEntry)
		err = shared.Err
	}

	if err != nil {
		return false, err
	}

	if !entry.found {
		return false, nil
	}

	remaining := entry.expiresAt.Sub(c.now())
	if remaining <= 0 {
		return false, nil
	}

	if err := json.Unmarshal(entry.value, destination); err != nil {
		return false, fmt.Errorf("decode database cache value for %q: %w", key, err)
	}

	c.memory.Set(key, entry.value, remaining)

	return true, nil
}

func (c *SharedCache) Set(ctx context.Context, key string, value any, ttl time.Duration) error {
	if _, err := sharedCacheItemID(key); err != nil {
		return err
	}

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

	writtenAt := c.now()
	expiresAt := writtenAt.Add(ttl)

	c.memory.Set(key, encoded, expiresAt.Sub(c.now()))
	if c.db == nil {
		return nil
	}

	if err := c.storeInDatabase(ctx, key, encoded, writtenAt, expiresAt); err != nil {
		return fmt.Errorf("store database cache value for %q: %w", key, err)
	}

	return nil
}

func (c *SharedCache) Delete(ctx context.Context, key string) error {
	if _, err := sharedCacheItemID(key); err != nil {
		return err
	}

	c.memory.Delete(key)

	if c.db == nil {
		return nil
	}

	if err := c.deleteFromDatabase(ctx, key); err != nil {
		return fmt.Errorf("delete database cache value for %q: %w", key, err)
	}

	return nil
}

func (c *SharedCache) canLoad() bool {
	return c.loader != nil || c.db != nil
}

func (c *SharedCache) load(ctx context.Context, key string) (sharedCacheDatabaseEntry, error) {
	if c.loader != nil {
		return c.loader(ctx, key)
	}

	return c.loadFromDatabase(ctx, key)
}

func (c *SharedCache) Close() {
	if c.db != nil {
		_ = c.db.Close()
	}
}
