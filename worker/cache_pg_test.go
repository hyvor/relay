package main

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestSharedCacheDatabaseRoundTrip(t *testing.T) {
	db := newTestCacheDB(t)
	_, err := db.Exec(`DELETE FROM cache_items WHERE item_id LIKE 'shared-v1:%'`)
	require.NoError(t, err)

	cache := NewSharedCache(db)
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
	db := newTestCacheDB(t)
	itemID, err := sharedCacheItemID("expired")
	require.NoError(t, err)
	insertCacheItem(t, db, itemID, []byte(`true`), 60, time.Now().Add(-time.Hour))

	cache := NewSharedCache(db)
	var value bool
	found, err := cache.Get(context.Background(), "expired", &value)
	require.NoError(t, err)
	assert.False(t, found)

	var count int
	require.NoError(t, db.QueryRow(`SELECT COUNT(*) FROM cache_items WHERE item_id = $1`, itemID).Scan(&count))
	assert.Zero(t, count)
}

func TestSharedCacheReadsSymfonyJsonEntryWithoutExtendingExpiry(t *testing.T) {
	db := newTestCacheDB(t)
	// The item ID a Symfony writer produces for "mta_sts:example.com".
	itemID := "shared-v1:h.ec06a31deb92499b60429301ecc5cac14f660cb4177110fbaf79f298122ee038"
	writtenAt := time.Now().Add(-30 * time.Minute)
	insertCacheItem(t, db, itemID, []byte(`{"name":"from-symfony"}`), 3600, writtenAt)

	cache := NewSharedCache(db)
	var value sharedCacheTestValue
	found, err := cache.Get(context.Background(), "mta_sts:example.com", &value)
	require.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "from-symfony", value.Name)

	item := cache.memory.Get("mta_sts:example.com")
	require.NotNil(t, item)
	assert.WithinDuration(t, writtenAt.Add(time.Hour), item.ExpiresAt(), 2*time.Second)
}

func newTestCacheDB(t *testing.T) *sql.DB {
	t.Helper()
	db, err := createNewTestDbConn()
	require.NoError(t, err)
	t.Cleanup(func() { db.Close() })
	return db
}

func insertCacheItem(t *testing.T, db *sql.DB, itemID string, data []byte, lifetime int64, writtenAt time.Time) {
	t.Helper()
	_, err := db.Exec(`
		INSERT INTO cache_items (item_id, item_data, item_lifetime, item_time)
		VALUES ($1, $2, $3, $4)
		ON CONFLICT (item_id) DO UPDATE SET item_data = EXCLUDED.item_data, item_lifetime = EXCLUDED.item_lifetime, item_time = EXCLUDED.item_time
	`, itemID, data, lifetime, writtenAt.Unix())
	require.NoError(t, err)
}
