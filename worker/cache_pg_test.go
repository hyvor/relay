package main

import (
	"context"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestSharedCacheDatabaseRoundTrip(t *testing.T) {
	db, err := createNewTestDbConn()
	require.NoError(t, err)
	t.Cleanup(func() { db.Close() })

	_, err = db.Exec(`DELETE FROM cache_items WHERE item_id LIKE 'relay-shared-v1:%'`)
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
