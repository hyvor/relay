package main

import (
	"context"
	"database/sql"
	"database/sql/driver"
	"fmt"
	"io"
	"strings"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestSharedCacheDatabaseRoundTrip(t *testing.T) {
	db, err := createNewTestDbConn()
	require.NoError(t, err)
	t.Cleanup(func() { db.Close() })

	_, err = db.Exec(`DELETE FROM cache_items WHERE item_id LIKE 'shared-v1:%'`)
	require.NoError(t, err)

	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)
	require.NoError(t, cache.Set(context.Background(), "mx:example.com", sharedCacheTestValue{Name: "mx.example.com"}, time.Hour))
	cache.memory.DeleteAll()

	var value sharedCacheTestValue
	found, err := cache.Get(context.Background(), "mx:example.com", &value)
	require.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "mx.example.com", value.Name)
	assert.NotNil(t, cache.memory.Get("mx:example.com"))
}

func TestSharedCacheDatabaseExpiredEntryIsDeletedSafely(t *testing.T) {
	db, err := createNewTestDbConn()
	require.NoError(t, err)
	t.Cleanup(func() { db.Close() })

	itemID, err := sharedCacheItemID("expired")
	require.NoError(t, err)
	_, err = db.Exec(`
		INSERT INTO cache_items (item_id, item_data, item_lifetime, item_time)
		VALUES ($1, $2, 60, $3)
		ON CONFLICT (item_id) DO UPDATE SET item_data = EXCLUDED.item_data, item_lifetime = EXCLUDED.item_lifetime, item_time = EXCLUDED.item_time
	`, itemID, []byte(`true`), time.Now().Add(-time.Hour).Unix())
	require.NoError(t, err)

	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)
	var value bool
	found, err := cache.Get(context.Background(), "expired", &value)
	require.NoError(t, err)
	assert.False(t, found)

	var count int
	require.NoError(t, db.QueryRow(`SELECT COUNT(*) FROM cache_items WHERE item_id = $1`, itemID).Scan(&count))
	assert.Zero(t, count)
}

func TestSharedCacheReadsSymfonyJsonEntryWithoutExtendingExpiry(t *testing.T) {
	db, err := createNewTestDbConn()
	require.NoError(t, err)
	t.Cleanup(func() { db.Close() })

	itemID, err := sharedCacheItemID("mta_sts:example.com")
	require.NoError(t, err)
	writtenAt := time.Now().Add(-30 * time.Minute).Unix()
	_, err = db.Exec(`
		INSERT INTO cache_items (item_id, item_data, item_lifetime, item_time)
		VALUES ($1, $2, 3600, $3)
		ON CONFLICT (item_id) DO UPDATE SET item_data = EXCLUDED.item_data, item_lifetime = EXCLUDED.item_lifetime, item_time = EXCLUDED.item_time
	`, itemID, []byte(`{"name":"from-symfony"}`), writtenAt)
	require.NoError(t, err)

	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)
	var value sharedCacheTestValue
	found, err := cache.Get(context.Background(), "mta_sts:example.com", &value)
	require.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "from-symfony", value.Name)

	item := cache.memory.Get("mta_sts:example.com")
	require.NotNil(t, item)
	assert.WithinDuration(t, time.Unix(writtenAt, 0).Add(time.Hour), item.ExpiresAt(), 2*time.Second)
}

func TestSharedCacheDatabaseLoadDoesNotRepublishAfterDelete(t *testing.T) {
	db, database := newSharedCacheBarrierDatabase(t, "key", []byte(`{"name":"old"}`), time.Now())
	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)

	first := getSharedCacheTestValue(cache, context.Background(), "key")
	database.waitForLoad(t, 1)

	require.NoError(t, cache.Delete(context.Background(), "key"))
	second := getSharedCacheTestValue(cache, context.Background(), "key")
	database.waitForLoad(t, 2)

	secondResult := waitForSharedCacheTestValue(t, second)
	require.NoError(t, secondResult.err)
	assert.False(t, secondResult.found)

	database.releaseLoad()
	firstResult := waitForSharedCacheTestValue(t, first)
	require.NoError(t, firstResult.err)
	assert.False(t, firstResult.found)
	assert.Nil(t, cache.memory.Get("key"))
}

func TestSharedCacheDatabaseLoadDoesNotRepublishAfterSet(t *testing.T) {
	db, database := newSharedCacheBarrierDatabase(t, "key", []byte(`{"name":"old"}`), time.Now())
	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)

	first := getSharedCacheTestValue(cache, context.Background(), "key")
	database.waitForLoad(t, 1)

	require.NoError(t, cache.Set(context.Background(), "key", sharedCacheTestValue{Name: "new"}, time.Hour))
	cache.writeMu.Lock()
	cache.memory.Delete("key")
	cache.writeMu.Unlock()

	second := getSharedCacheTestValue(cache, context.Background(), "key")
	database.waitForLoad(t, 2)
	secondResult := waitForSharedCacheTestValue(t, second)
	require.NoError(t, secondResult.err)
	assert.True(t, secondResult.found)
	assert.Equal(t, "new", secondResult.value.Name)

	database.releaseLoad()
	firstResult := waitForSharedCacheTestValue(t, first)
	require.NoError(t, firstResult.err)
	assert.True(t, firstResult.found)
	assert.Equal(t, "new", firstResult.value.Name)
}

func TestSharedCacheDatabaseHydrationUsesAbsoluteExpiry(t *testing.T) {
	writtenAt := time.Unix(1_700_000_000, 0)
	db, database := newSharedCacheBarrierDatabase(t, "key", []byte(`{"name":"database"}`), writtenAt)
	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)

	clock := newSharedCacheBarrierClock(writtenAt)
	defer clock.releaseFirstCall()
	cache.now = clock.now

	result := getSharedCacheTestValue(cache, context.Background(), "key")
	database.waitForLoad(t, 1)

	cache.writeMu.Lock()
	database.releaseLoad()
	waitForSharedCacheSignal(t, clock.firstCall)
	clock.set(writtenAt.Add(45 * time.Minute))
	clock.releaseFirstCall()
	cache.writeMu.Unlock()

	loaded := waitForSharedCacheTestValue(t, result)
	require.NoError(t, loaded.err)
	assert.True(t, loaded.found)

	item := cache.memory.Get("key")
	require.NotNil(t, item)
	assert.Equal(t, 15*time.Minute, item.TTL())
}

func TestSharedCacheDatabaseLoadIsNotCanceledByFirstWaiter(t *testing.T) {
	db, database := newSharedCacheBarrierDatabase(t, "key", []byte(`{"name":"database"}`), time.Now())
	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)

	firstCtx, cancelFirst := context.WithCancel(context.Background())
	first := getSharedCacheTestValue(cache, firstCtx, "key")
	database.waitForLoad(t, 1)

	secondCtx := newSharedCacheObservedContext(context.Background())
	second := getSharedCacheTestValue(cache, secondCtx, "key")
	waitForSharedCacheSignal(t, secondCtx.doneCalled)

	cancelFirst()
	firstResult := waitForSharedCacheTestValue(t, first)
	assert.ErrorIs(t, firstResult.err, context.Canceled)

	database.releaseLoad()
	secondResult := waitForSharedCacheTestValue(t, second)
	require.NoError(t, secondResult.err)
	assert.True(t, secondResult.found)
	assert.Equal(t, "database", secondResult.value.Name)
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

type sharedCacheBarrierClock struct {
	mu               sync.Mutex
	current          time.Time
	callCount        int
	firstCall        chan struct{}
	releaseFirst     chan struct{}
	releaseFirstOnce sync.Once
}

func newSharedCacheBarrierClock(now time.Time) *sharedCacheBarrierClock {
	return &sharedCacheBarrierClock{
		current:      now,
		firstCall:    make(chan struct{}),
		releaseFirst: make(chan struct{}),
	}
}

func (c *sharedCacheBarrierClock) now() time.Time {
	c.mu.Lock()
	current := c.current
	c.callCount++
	firstCall := c.callCount == 1
	c.mu.Unlock()
	if firstCall {
		close(c.firstCall)
		<-c.releaseFirst
	}
	return current
}

func (c *sharedCacheBarrierClock) set(now time.Time) {
	c.mu.Lock()
	c.current = now
	c.mu.Unlock()
}

func (c *sharedCacheBarrierClock) releaseFirstCall() {
	c.releaseFirstOnce.Do(func() { close(c.releaseFirst) })
}

var sharedCacheTestDriverSequence atomic.Uint64

type sharedCacheBarrierDatabase struct {
	mu          sync.Mutex
	itemID      string
	value       []byte
	lifetime    int64
	writtenAt   int64
	found       bool
	loadCount   int
	loadStarted chan int
	release     chan struct{}
	releaseOnce sync.Once
}

func newSharedCacheBarrierDatabase(t *testing.T, key string, value []byte, writtenAt time.Time) (*sql.DB, *sharedCacheBarrierDatabase) {
	t.Helper()
	itemID, err := sharedCacheItemID(key)
	require.NoError(t, err)
	database := &sharedCacheBarrierDatabase{
		itemID:      itemID,
		value:       append([]byte(nil), value...),
		lifetime:    int64(time.Hour / time.Second),
		writtenAt:   writtenAt.Unix(),
		found:       true,
		loadStarted: make(chan int, 16),
		release:     make(chan struct{}),
	}
	driverName := fmt.Sprintf("shared-cache-test-%d", sharedCacheTestDriverSequence.Add(1))
	sql.Register(driverName, &sharedCacheBarrierDriver{database: database})
	db, err := sql.Open(driverName, "")
	require.NoError(t, err)
	t.Cleanup(func() {
		database.releaseLoad()
		_ = db.Close()
	})
	return db, database
}

func (d *sharedCacheBarrierDatabase) waitForLoad(t *testing.T, expected int) {
	t.Helper()
	select {
	case actual := <-d.loadStarted:
		require.Equal(t, expected, actual)
	case <-time.After(5 * time.Second):
		t.Fatal("timed out waiting for database load")
	}
}

func (d *sharedCacheBarrierDatabase) releaseLoad() {
	d.releaseOnce.Do(func() { close(d.release) })
}

type sharedCacheBarrierDriver struct {
	database *sharedCacheBarrierDatabase
}

func (d *sharedCacheBarrierDriver) Open(string) (driver.Conn, error) {
	return &sharedCacheBarrierConnection{database: d.database}, nil
}

type sharedCacheBarrierConnection struct {
	database *sharedCacheBarrierDatabase
}

func (c *sharedCacheBarrierConnection) Prepare(string) (driver.Stmt, error) {
	return nil, fmt.Errorf("prepared statements are not supported")
}

func (c *sharedCacheBarrierConnection) Close() error {
	return nil
}

func (c *sharedCacheBarrierConnection) Begin() (driver.Tx, error) {
	return nil, fmt.Errorf("transactions are not supported")
}

func (c *sharedCacheBarrierConnection) QueryContext(ctx context.Context, query string, args []driver.NamedValue) (driver.Rows, error) {
	if !strings.Contains(query, "SELECT item_data, item_lifetime, item_time") {
		return nil, fmt.Errorf("unexpected query: %s", query)
	}

	itemID, _ := args[0].Value.(string)
	c.database.mu.Lock()
	c.database.loadCount++
	loadCount := c.database.loadCount
	found := c.database.found && c.database.itemID == itemID
	value := append([]byte(nil), c.database.value...)
	lifetime := c.database.lifetime
	writtenAt := c.database.writtenAt
	c.database.mu.Unlock()
	c.database.loadStarted <- loadCount

	if loadCount == 1 {
		select {
		case <-c.database.release:
		case <-ctx.Done():
			return nil, ctx.Err()
		}
	}

	return &sharedCacheBarrierRows{
		found: found,
		values: []driver.Value{
			value,
			lifetime,
			writtenAt,
		},
	}, nil
}

func (c *sharedCacheBarrierConnection) ExecContext(ctx context.Context, query string, args []driver.NamedValue) (driver.Result, error) {
	select {
	case <-ctx.Done():
		return nil, ctx.Err()
	default:
	}

	itemID, _ := args[0].Value.(string)
	c.database.mu.Lock()
	defer c.database.mu.Unlock()

	switch {
	case strings.Contains(query, "INSERT INTO cache_items"):
		value, _ := args[1].Value.([]byte)
		lifetime, _ := args[2].Value.(int64)
		writtenAt, _ := args[3].Value.(int64)
		c.database.itemID = itemID
		c.database.value = append([]byte(nil), value...)
		c.database.lifetime = lifetime
		c.database.writtenAt = writtenAt
		c.database.found = true
	case strings.Contains(query, "DELETE FROM cache_items"):
		if c.database.itemID == itemID {
			c.database.found = false
		}
	default:
		return nil, fmt.Errorf("unexpected exec: %s", query)
	}

	return driver.RowsAffected(1), nil
}

type sharedCacheBarrierRows struct {
	found  bool
	read   bool
	values []driver.Value
}

func (r *sharedCacheBarrierRows) Columns() []string {
	return []string{"item_data", "item_lifetime", "item_time"}
}

func (r *sharedCacheBarrierRows) Close() error {
	return nil
}

func (r *sharedCacheBarrierRows) Next(destination []driver.Value) error {
	if !r.found || r.read {
		return io.EOF
	}
	r.read = true
	copy(destination, r.values)
	return nil
}
