package main

import (
	"context"
	"errors"
	"strings"
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

func TestSharedCacheMemoryHitDoesNotWaitForBlockedDatabaseLoad(t *testing.T) {
	db, database := newSharedCacheBarrierDatabase(t, "blocked", []byte(`true`), time.Now())
	cache := NewSharedCache(db)
	t.Cleanup(cache.Close)
	cache.memory.Set("cached", []byte(`true`), time.Hour)

	blocked := make(chan struct{})
	go func() {
		var value bool
		_, _ = cache.Get(context.Background(), "blocked", &value)
		close(blocked)
	}()
	database.waitForLoad(t, 1)

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
		t.Fatal("memory hit waited for a blocked database load on another key")
	}
	database.releaseLoad()
	<-blocked
}

func TestSharedCacheRejectsOversizedValues(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)

	err := cache.Set(context.Background(), "key", strings.Repeat("x", sharedCacheMaxValueSize), time.Hour)
	assert.ErrorIs(t, err, ErrCacheValueTooLarge)
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

func TestSharedCacheSetReturnsEncodingError(t *testing.T) {
	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)

	err := cache.Set(context.Background(), "key", func() {}, time.Hour)
	assert.Error(t, err)
	assert.False(t, errors.Is(err, ErrCacheValueTooLarge))
}
