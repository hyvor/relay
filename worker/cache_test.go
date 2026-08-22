package main

import (
	"context"
	"fmt"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

type cacheTestValue struct {
	Foo string `json:"foo"`
}

func TestCacheMemoryTier_HitAndMiss(t *testing.T) {
	cacheClearForTest()

	var dest cacheTestValue
	found, err := cacheGet(context.Background(), nil, "missing-key", &dest)
	assert.NoError(t, err)
	assert.False(t, found)

	err = cacheSet(context.Background(), nil, "some-key", cacheTestValue{Foo: "bar"}, time.Hour)
	assert.NoError(t, err)

	found, err = cacheGet(context.Background(), nil, "some-key", &dest)
	assert.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "bar", dest.Foo)
}

func TestCacheMemoryTier_Expiry(t *testing.T) {
	cacheClearForTest()

	err := cacheSet(context.Background(), nil, "expiring-key", cacheTestValue{Foo: "bar"}, -1*time.Second)
	assert.NoError(t, err)

	var dest cacheTestValue
	found, err := cacheGet(context.Background(), nil, "expiring-key", &dest)
	assert.NoError(t, err)
	assert.False(t, found)
}

func TestCacheMemoryTier_LruEviction(t *testing.T) {
	cacheClearForTest()
	defer cacheClearForTest()

	// fill beyond capacity to force eviction of the oldest entries
	for i := 0; i < cacheMemorySize+10; i++ {
		key := fmt.Sprintf("key-%d", i)
		_ = cacheSet(context.Background(), nil, key, cacheTestValue{Foo: "x"}, time.Hour)
	}

	assert.Equal(t, cacheMemorySize, cacheMemory.Len())
}

func TestCachePgTier_RoundTrip(t *testing.T) {
	err := truncateTestDb()
	assert.NoError(t, err)
	cacheClearForTest()

	conn, err := createNewTestDbConn()
	assert.NoError(t, err)
	defer conn.Close()

	err = cacheSet(context.Background(), conn, "db-key", cacheTestValue{Foo: "baz"}, time.Hour)
	assert.NoError(t, err)

	// clear the memory tier so the read is forced through the DB tier
	cacheClearForTest()

	var dest cacheTestValue
	found, err := cacheGet(context.Background(), conn, "db-key", &dest)
	assert.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "baz", dest.Foo)

	// the DB hit should have populated the memory tier too
	_, ok := cacheMemory.Get("db-key")
	assert.True(t, ok)
}

func TestCachePgTier_ExpiredRowTreatedAsMiss(t *testing.T) {
	err := truncateTestDb()
	assert.NoError(t, err)
	cacheClearForTest()

	conn, err := createNewTestDbConn()
	assert.NoError(t, err)
	defer conn.Close()

	// insert a row that already expired an hour ago
	_, err = conn.Exec(`
		INSERT INTO cache_items (item_id, item_data, item_lifetime, item_time)
		VALUES ($1, $2, $3, $4)
	`, "expired-db-key", []byte(`{"foo":"old"}`), 60, time.Now().Add(-1*time.Hour).Unix())
	assert.NoError(t, err)

	var dest cacheTestValue
	found, err := cacheGet(context.Background(), conn, "expired-db-key", &dest)
	assert.NoError(t, err)
	assert.False(t, found)

	// the expired row should have been pruned
	var count int
	err = conn.QueryRow(`SELECT COUNT(*) FROM cache_items WHERE item_id = $1`, "expired-db-key").Scan(&count)
	assert.NoError(t, err)
	assert.Equal(t, 0, count)
}

func TestCachePgTier_Upsert(t *testing.T) {
	err := truncateTestDb()
	assert.NoError(t, err)
	cacheClearForTest()

	conn, err := createNewTestDbConn()
	assert.NoError(t, err)
	defer conn.Close()

	err = cacheSet(context.Background(), conn, "upsert-key", cacheTestValue{Foo: "first"}, time.Hour)
	assert.NoError(t, err)

	err = cacheSet(context.Background(), conn, "upsert-key", cacheTestValue{Foo: "second"}, time.Hour)
	assert.NoError(t, err)

	var count int
	err = conn.QueryRow(`SELECT COUNT(*) FROM cache_items WHERE item_id = $1`, "upsert-key").Scan(&count)
	assert.NoError(t, err)
	assert.Equal(t, 1, count)

	cacheClearForTest()

	var dest cacheTestValue
	found, err := cacheGet(context.Background(), conn, "upsert-key", &dest)
	assert.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, "second", dest.Foo)
}
