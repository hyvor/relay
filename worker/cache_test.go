package main

import (
	"context"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

type sharedCacheTestValue struct {
	Name string `json:"name"`
}

func TestSharedCacheMemoryRoundTrip(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)

	require.NoError(t, cache.Set(context.Background(), "mx:example.com", sharedCacheTestValue{Name: "mx.example.com"}, time.Hour))

	var value sharedCacheTestValue
	found, err := cache.Get(context.Background(), "mx:example.com", &value)

	require.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "mx.example.com", value.Name)
}

func TestSharedCacheMemoryExpiryDoesNotSlide(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)

	require.NoError(t, cache.Set(context.Background(), "key", true, time.Hour))

	item := cache.memory.Get("key")
	require.NotNil(t, item)

	expiresAt := item.ExpiresAt()

	var value bool
	found, err := cache.Get(context.Background(), "key", &value)

	require.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, expiresAt, cache.memory.Get("key").ExpiresAt())
}

func TestSharedCacheDelete(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)

	require.NoError(t, cache.Set(context.Background(), "key", true, time.Hour))
	require.NoError(t, cache.Delete(context.Background(), "key"))

	var value bool
	found, err := cache.Get(context.Background(), "key", &value)
	require.NoError(t, err)
	assert.False(t, found)
}

func TestSharedCacheRejectsOversizedValues(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)

	// The two surrounding quotes are what push these over and under the limit.
	err := cache.Set(context.Background(), "key", strings.Repeat("x", sharedCacheMaxValueSize), time.Hour)
	assert.ErrorIs(t, err, ErrCacheValueTooLarge)

	err = cache.Set(context.Background(), "key", strings.Repeat("x", sharedCacheMaxValueSize-2), time.Hour)
	assert.NoError(t, err)
}

func TestSharedCacheItemID(t *testing.T) {
	itemID, err := sharedCacheItemID("mx:example.com")
	require.NoError(t, err)
	assert.Equal(t, "shared-v1:h.e4b96a16d2ff7506f1554ec72f5aeb3e347284fe302dd2dd90af34c5b9276e77", itemID)

	_, err = sharedCacheItemID("")
	assert.Error(t, err)
}

func TestSharedCacheMemoryDecodeErrorRemovesEntry(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	cache.memory.Set("key", []byte("{"), time.Hour)

	var value sharedCacheTestValue
	found, err := cache.Get(context.Background(), "key", &value)
	assert.False(t, found)
	assert.Error(t, err)
	assert.Nil(t, cache.memory.Get("key"))
}

func TestSharedCacheLoadDoesNotRepublishAfterDelete(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	loader := newSharedCacheTestLoader(t, cache, []byte(`{"name":"old"}`), time.Now().Add(time.Hour))

	// The load is already in flight when the delete lands, so the entry it
	// returns is stale by the time it arrives and must not reach memory.
	result := getSharedCacheTestValue(cache, context.Background(), "key")
	loader.waitForLoad(t)

	require.NoError(t, cache.Delete(context.Background(), "key"))
	loader.deleteEntry()
	loader.releaseLoad()

	loaded := waitForSharedCacheTestValue(t, result)
	require.NoError(t, loaded.err)
	assert.False(t, loaded.found)
	assert.Nil(t, cache.memory.Get("key"))
}

func TestSharedCacheLoadDoesNotRepublishAfterSet(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	loader := newSharedCacheTestLoader(t, cache, []byte(`{"name":"old"}`), time.Now().Add(time.Hour))

	// Same race as the delete case: the in-flight load carries the old value and
	// must not overwrite the newer one the set just stored.
	result := getSharedCacheTestValue(cache, context.Background(), "key")
	loader.waitForLoad(t)

	require.NoError(t, cache.Set(context.Background(), "key", sharedCacheTestValue{Name: "new"}, time.Hour))
	loader.releaseLoad()

	loaded := waitForSharedCacheTestValue(t, result)
	require.NoError(t, loaded.err)
	assert.True(t, loaded.found)
	assert.Equal(t, "new", loaded.value.Name)

	item := cache.memory.Get("key")
	require.NotNil(t, item)
	assert.JSONEq(t, `{"name":"new"}`, string(item.Value()))
}

func TestSharedCacheHydrationUsesAbsoluteExpiry(t *testing.T) {
	writtenAt := time.Unix(1_700_000_000, 0)
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	loader := newSharedCacheTestLoader(t, cache, []byte(`{"name":"database"}`), writtenAt.Add(time.Hour))

	// The entry was written 45 minutes ago with a one hour lifetime, so memory
	// inherits the remaining 15 minutes rather than a fresh hour.
	cache.now = func() time.Time { return writtenAt.Add(45 * time.Minute) }
	loader.releaseLoad()

	var value sharedCacheTestValue
	found, err := cache.Get(context.Background(), "key", &value)
	require.NoError(t, err)
	assert.True(t, found)

	item := cache.memory.Get("key")
	require.NotNil(t, item)
	assert.Equal(t, 15*time.Minute, item.TTL())
}

func TestSharedCacheLoadIsNotCanceledByFirstWaiter(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	loader := newSharedCacheTestLoader(t, cache, []byte(`{"name":"database"}`), time.Now().Add(time.Hour))

	firstCtx, cancelFirst := context.WithCancel(context.Background())
	first := getSharedCacheTestValue(cache, firstCtx, "key")
	loader.waitForLoad(t)

	// The second waiter shares the first one's load, so it must survive the
	// first one being canceled.
	secondCtx := newSharedCacheObservedContext(context.Background())
	second := getSharedCacheTestValue(cache, secondCtx, "key")
	waitForSharedCacheSignal(t, secondCtx.doneCalled)

	cancelFirst()
	firstResult := waitForSharedCacheTestValue(t, first)
	assert.ErrorIs(t, firstResult.err, context.Canceled)

	loader.releaseLoad()
	secondResult := waitForSharedCacheTestValue(t, second)
	require.NoError(t, secondResult.err)
	assert.True(t, secondResult.found)
	assert.Equal(t, "database", secondResult.value.Name)
}

func TestSharedCacheMemoryHitDoesNotWaitForBlockedLoad(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	loader := newSharedCacheTestLoader(t, cache, []byte(`true`), time.Now().Add(time.Hour))
	cache.memory.Set("cached", []byte(`true`), time.Hour)

	blocked := make(chan struct{})
	go func() {
		var value bool
		_, _ = cache.Get(context.Background(), "blocked", &value)
		close(blocked)
	}()
	loader.waitForLoad(t)

	result := make(chan bool, 1)
	go func() {
		var value bool
		found, err := cache.Get(context.Background(), "cached", &value)
		result <- found && err == nil && value
	}()

	select {
	case found := <-result:
		assert.True(t, found)
	case <-time.After(time.Second):
		t.Fatal("memory hit waited for a blocked load on another key")
	}
	loader.releaseLoad()
	<-blocked
}

// sharedCacheTestLoader stands in for a database read. The first load parks
// until releaseLoad, which is what lets a test land a write while a load is
// still in flight.
type sharedCacheTestLoader struct {
	mu          sync.Mutex
	entry       sharedCacheDatabaseEntry
	calls       int
	started     chan struct{}
	release     chan struct{}
	releaseOnce sync.Once
}

func newSharedCacheTestLoader(t *testing.T, cache *SharedCache, value []byte, expiresAt time.Time) *sharedCacheTestLoader {
	t.Helper()
	loader := &sharedCacheTestLoader{
		entry:   sharedCacheDatabaseEntry{value: value, found: true, expiresAt: expiresAt},
		started: make(chan struct{}, 16),
		release: make(chan struct{}),
	}
	cache.loader = loader.load
	t.Cleanup(loader.releaseLoad)
	return loader
}

func (l *sharedCacheTestLoader) load(ctx context.Context, _ string) (sharedCacheDatabaseEntry, error) {
	l.mu.Lock()
	l.calls++
	first := l.calls == 1
	entry := l.entry
	l.mu.Unlock()

	l.started <- struct{}{}

	if first {
		select {
		case <-l.release:
		case <-ctx.Done():
			return sharedCacheDatabaseEntry{}, ctx.Err()
		}
	}

	return entry, nil
}

// deleteEntry makes every later load report a miss, the way a deleted row would.
func (l *sharedCacheTestLoader) deleteEntry() {
	l.mu.Lock()
	defer l.mu.Unlock()
	l.entry = sharedCacheDatabaseEntry{}
}

func (l *sharedCacheTestLoader) releaseLoad() {
	l.releaseOnce.Do(func() { close(l.release) })
}

func (l *sharedCacheTestLoader) waitForLoad(t *testing.T) {
	t.Helper()
	waitForSharedCacheSignal(t, l.started)
}

type sharedCacheTestGetResult struct {
	value sharedCacheTestValue
	found bool
	err   error
}

func getSharedCacheTestValue(cache *SharedCache, ctx context.Context, key string) <-chan sharedCacheTestGetResult {
	result := make(chan sharedCacheTestGetResult, 1)
	go func() {
		var value sharedCacheTestValue
		found, err := cache.Get(ctx, key, &value)
		result <- sharedCacheTestGetResult{value: value, found: found, err: err}
	}()
	return result
}

func waitForSharedCacheTestValue(t *testing.T, result <-chan sharedCacheTestGetResult) sharedCacheTestGetResult {
	t.Helper()
	select {
	case value := <-result:
		return value
	case <-time.After(5 * time.Second):
		t.Fatal("timed out waiting for cache result")
		return sharedCacheTestGetResult{}
	}
}

func waitForSharedCacheSignal(t *testing.T, signal <-chan struct{}) {
	t.Helper()
	select {
	case <-signal:
	case <-time.After(5 * time.Second):
		t.Fatal("timed out waiting for cache test barrier")
	}
}

// sharedCacheObservedContext reports when Get first selects on Done, which is
// how a test knows a waiter has actually parked on an in-flight load.
type sharedCacheObservedContext struct {
	context.Context
	doneCalled chan struct{}
	doneOnce   sync.Once
}

func newSharedCacheObservedContext(ctx context.Context) *sharedCacheObservedContext {
	return &sharedCacheObservedContext{Context: ctx, doneCalled: make(chan struct{})}
}

func (c *sharedCacheObservedContext) Done() <-chan struct{} {
	c.doneOnce.Do(func() { close(c.doneCalled) })
	return c.Context.Done()
}
