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
	entry   sharedCacheDatabaseEntry
	version uint64
}

// sharedCacheKeyState serializes access to a single key. version is bumped by
// every write so a Get can tell whether its in-flight database load raced with
// one. refs tracks how many operations hold the state so it can be dropped from
// the map once the key goes idle.
type sharedCacheKeyState struct {
	sync.Mutex
	version uint64
	refs    int
}

type SharedCache struct {
	dbMu   sync.RWMutex
	db     *sql.DB
	memory *ttlcache.Cache[string, sharedCacheMemoryValue]
	loads  singleflight.Group
	now    func() time.Time
	// loader stands in for loadFromDatabase so tests can hold a load open;
	// production never sets it.
	loader      func(context.Context, string) (sharedCacheDatabaseEntry, error)
	cancel      context.CancelFunc
	wg          sync.WaitGroup
	keyStatesMu sync.Mutex
	keyStates   map[string]*sharedCacheKeyState
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

// this attaches the process cache to a process-owned
// database handle, the caller must not close the handle when this returns true.
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
		db:        db,
		memory:    memory,
		now:       time.Now,
		keyStates: make(map[string]*sharedCacheKeyState),
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

	state := c.keyState(key)
	defer c.releaseKeyState(key, state)

	for {
		state.Lock()

		item := c.memory.Get(key)
		if item != nil {
			if err := json.Unmarshal(item.Value(), destination); err != nil {
				c.memory.Delete(key)
				state.Unlock()
				return false, fmt.Errorf("decode memory cache value for %q: %w", key, err)
			}
			state.Unlock()
			return true, nil
		}

		version := state.version
		state.Unlock()

		if !c.canLoad() {
			return false, nil
		}

		// The load is shared with other waiters, so it must outlive whichever
		// caller happens to own it. We still give up on our own ctx below.
		loadCtx := context.WithoutCancel(ctx)
		result := c.loads.DoChan(key, func() (any, error) {
			entry, err := c.load(loadCtx, key)
			return sharedCacheLoad{entry: entry, version: version}, err
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

		state.Lock()

		// A Set/Delete landed while we were reading the database, so the row we
		// got is already stale. Retry instead of caching it.
		if state.version != load.version {
			state.Unlock()
			continue
		}

		if err != nil {
			state.Unlock()
			return false, err
		}

		if !load.entry.found {
			state.Unlock()
			return false, nil
		}

		remaining := load.entry.expiresAt.Sub(c.now())
		if remaining <= 0 {
			state.Unlock()
			return false, nil
		}

		if err := json.Unmarshal(load.entry.value, destination); err != nil {
			state.Unlock()
			return false, fmt.Errorf("decode database cache value for %q: %w", key, err)
		}

		c.memory.Set(key, load.entry.value, remaining)
		state.Unlock()

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

	encoded, err := json.Marshal(value)
	if err != nil {
		return fmt.Errorf("encode cache value for %q: %w", key, err)
	}

	if len(encoded) > sharedCacheMaxValueSize {
		return fmt.Errorf("%w: %d bytes", ErrCacheValueTooLarge, len(encoded))
	}

	state := c.keyState(key)
	defer c.releaseKeyState(key, state)

	state.Lock()
	defer state.Unlock()

	state.version++
	writtenAt := c.now()
	expiresAt := writtenAt.Add(ttl)

	c.memory.Set(key, encoded, expiresAt.Sub(c.now()))
	if c.database() == nil {
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

	state := c.keyState(key)
	defer c.releaseKeyState(key, state)

	state.Lock()
	defer state.Unlock()

	state.version++
	c.memory.Delete(key)

	if c.database() == nil {
		return nil
	}

	if err := c.deleteFromDatabase(ctx, key); err != nil {
		return fmt.Errorf("delete database cache value for %q: %w", key, err)
	}

	return nil
}

func (c *SharedCache) canLoad() bool {
	return c.loader != nil || c.database() != nil
}

func (c *SharedCache) load(ctx context.Context, key string) (sharedCacheDatabaseEntry, error) {
	if c.loader != nil {
		return c.loader(ctx, key)
	}

	return c.loadFromDatabase(ctx, key)
}

func (c *SharedCache) keyState(key string) *sharedCacheKeyState {
	c.keyStatesMu.Lock()
	defer c.keyStatesMu.Unlock()

	state := c.keyStates[key]
	if state == nil {
		state = &sharedCacheKeyState{}
		c.keyStates[key] = state
	}

	state.refs++

	return state
}

func (c *SharedCache) releaseKeyState(key string, state *sharedCacheKeyState) {
	c.keyStatesMu.Lock()
	defer c.keyStatesMu.Unlock()

	state.refs--
	if state.refs == 0 {
		delete(c.keyStates, key)
	}
}
