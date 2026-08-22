package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"log/slog"
	"time"

	"github.com/hashicorp/golang-lru/v2/expirable"
)

// A minimal two-tier cache, mirroring the Doctrine DBAL cache adapter Symfony
// uses on the PHP side (see backend/migrations/Version20250731232854.php).
//
// Tier 1: an in-process, size-bounded LRU (fast, but not shared across
// processes and lost on restart).
// Tier 2: the shared `cache_items` Postgres table (slower, but shared across
// all worker processes and durable).
//
// Callers need different TTLs per key (MX/DANE are fixed, MTA-STS is
// variable), so the LRU's own TTL support isn't used (ttl=0 disables it,
// see expirable.NewLRU); expiry is tracked and checked manually via
// cacheEntry.ExpiresAt instead. The LRU is still useful purely to bound
// memory via size-based eviction.
const cacheMemorySize = 10_000

// cacheNoExpiryMemoryTtl is used to populate the memory tier from a DB row
// that has no lifetime set (never expires). Purely a memory-tier retention
// window; correctness comes from the DB row itself having no expiry.
const cacheNoExpiryMemoryTtl = 24 * time.Hour

type cacheEntry struct {
	Data      []byte
	ExpiresAt time.Time
}

var cacheMemory = expirable.NewLRU[string, cacheEntry](cacheMemorySize, nil, 0)

// cacheClearForTest resets the in-memory tier. Only meant to be called from tests.
func cacheClearForTest() {
	cacheMemory.Purge()
}

// cacheGet looks up key, first in memory, then (if db is non-nil) in the
// database, unmarshalling the stored JSON into dest on a hit. Cache/DB
// errors are logged and treated as a miss - a cache problem must never fail
// the caller.
func cacheGet(ctx context.Context, db *sql.DB, key string, dest any) (bool, error) {

	if entry, ok := cacheMemory.Get(key); ok {
		if entry.ExpiresAt.After(time.Now()) {
			if err := json.Unmarshal(entry.Data, dest); err != nil {
				return false, err
			}
			return true, nil
		}
		cacheMemory.Remove(key)
	}

	if db == nil {
		return false, nil
	}

	data, expiresAt, found, err := cachePgGet(ctx, db, key)

	if err != nil {
		slog.Default().Error("cache: db read failed", "key", key, "error", err)
		return false, nil
	}

	if !found {
		return false, nil
	}

	memExpiresAt := expiresAt
	if memExpiresAt.IsZero() {
		memExpiresAt = time.Now().Add(cacheNoExpiryMemoryTtl)
	}
	cacheMemory.Add(key, cacheEntry{Data: data, ExpiresAt: memExpiresAt})

	if err := json.Unmarshal(data, dest); err != nil {
		return false, err
	}

	return true, nil

}

// cacheSet writes value (marshalled as JSON) to both cache tiers with the
// given TTL. DB write errors are logged but not returned - the in-memory
// write still succeeds.
func cacheSet(ctx context.Context, db *sql.DB, key string, value any, ttl time.Duration) error {

	data, err := json.Marshal(value)
	if err != nil {
		return err
	}

	cacheMemory.Add(key, cacheEntry{
		Data:      data,
		ExpiresAt: time.Now().Add(ttl),
	})

	if db == nil {
		return nil
	}

	if err := cachePgSet(ctx, db, key, data, ttl); err != nil {
		slog.Default().Error("cache: db write failed", "key", key, "error", err)
	}

	return nil

}
