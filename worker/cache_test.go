package main

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
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

	require.NoError(t, cache.Set(context.Background(), "key", true, 40*time.Millisecond))
	time.Sleep(25 * time.Millisecond)

	var value bool
	found, err := cache.Get(context.Background(), "key", &value)
	require.NoError(t, err)
	assert.True(t, found)

	time.Sleep(25 * time.Millisecond)
	found, err = cache.Get(context.Background(), "key", &value)
	require.NoError(t, err)
	assert.False(t, found)
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

	err := cache.Set(context.Background(), "key", strings.Repeat("x", sharedCacheMaxValueSize), time.Hour)
	assert.ErrorIs(t, err, ErrCacheValueTooLarge)
}

func TestSharedCacheItemID(t *testing.T) {
	itemID, err := sharedCacheItemID("mx:example.com")
	require.NoError(t, err)
	digest := sha256.Sum256([]byte("mx:example.com"))
	assert.Equal(t, "shared-v1:h."+hex.EncodeToString(digest[:]), itemID)

	longID, err := sharedCacheItemID(strings.Repeat("a", 300))
	require.NoError(t, err)
	assert.LessOrEqual(t, len(longID), sharedCacheMaxItemIDLen)
	assert.True(t, strings.HasPrefix(longID, "shared-v1:h."))

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
