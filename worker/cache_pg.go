package main

import (
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"time"
)

const (
	sharedCacheNamespace       = "relay-shared-v1"
	sharedCacheMaxItemIDLen    = 255
	sharedCacheDatabaseTimeout = 2 * time.Second
)

type sharedCacheDatabaseEntry struct {
	value     []byte
	found     bool
	expiresAt time.Time
}

func sharedCacheItemID(key string) (string, error) {
	if key == "" {
		return "", fmt.Errorf("cache key cannot be empty")
	}

	encoded := "k." + base64.RawURLEncoding.EncodeToString([]byte(key))
	itemID := sharedCacheNamespace + ":" + encoded
	if len(itemID) <= sharedCacheMaxItemIDLen {
		return itemID, nil
	}

	digest := sha256.Sum256([]byte(key))
	return sharedCacheNamespace + ":h." + hex.EncodeToString(digest[:]), nil
}

func (c *SharedCache) loadFromDatabase(ctx context.Context, key string) (sharedCacheDatabaseEntry, error) {
	ctx, cancel := context.WithTimeout(ctx, sharedCacheDatabaseTimeout)
	defer cancel()
	db := c.database()
	if db == nil {
		return sharedCacheDatabaseEntry{}, nil
	}
	itemID, err := sharedCacheItemID(key)
	if err != nil {
		return sharedCacheDatabaseEntry{}, err
	}

	var value []byte
	var lifetime int64
	var writtenAt int64
	err = db.QueryRowContext(ctx, `
		SELECT item_data, item_lifetime, item_time
		FROM cache_items
		WHERE item_id = $1 AND item_lifetime IS NOT NULL
	`, itemID).Scan(&value, &lifetime, &writtenAt)
	if err == sql.ErrNoRows {
		return sharedCacheDatabaseEntry{}, nil
	}
	if err != nil {
		return sharedCacheDatabaseEntry{}, err
	}

	expiresAt := time.Unix(writtenAt, 0).Add(time.Duration(lifetime) * time.Second)
	remaining := expiresAt.Sub(c.now())
	if remaining <= 0 {
		_, deleteErr := db.ExecContext(ctx, `
			DELETE FROM cache_items
			WHERE item_id = $1
				AND item_lifetime IS NOT NULL
				AND item_lifetime + item_time <= $2
		`, itemID, c.now().Unix())
		if deleteErr != nil {
			return sharedCacheDatabaseEntry{}, deleteErr
		}
		return sharedCacheDatabaseEntry{}, nil
	}
	if len(value) > sharedCacheMaxValueSize {
		return sharedCacheDatabaseEntry{}, fmt.Errorf("%w: %d bytes", ErrCacheValueTooLarge, len(value))
	}
	if !json.Valid(value) {
		_, deleteErr := db.ExecContext(ctx, `
			DELETE FROM cache_items WHERE item_id = $1 AND item_data = $2
		`, itemID, value)
		if deleteErr != nil {
			return sharedCacheDatabaseEntry{}, deleteErr
		}
		return sharedCacheDatabaseEntry{}, fmt.Errorf("invalid JSON in database cache value for %q", key)
	}

	return sharedCacheDatabaseEntry{value: value, found: true, expiresAt: expiresAt}, nil
}

func (c *SharedCache) storeInDatabase(ctx context.Context, key string, value []byte, ttl time.Duration) error {
	ctx, cancel := context.WithTimeout(ctx, sharedCacheDatabaseTimeout)
	defer cancel()
	db := c.database()
	if db == nil {
		return nil
	}
	itemID, err := sharedCacheItemID(key)
	if err != nil {
		return err
	}

	// DoctrineDbalAdapter stores whole-second lifetimes and write timestamps.
	lifetime := int64((ttl + time.Second - 1) / time.Second)
	_, err = db.ExecContext(ctx, `
		INSERT INTO cache_items (item_id, item_data, item_lifetime, item_time)
		VALUES ($1, $2, $3, $4)
		ON CONFLICT (item_id) DO UPDATE SET
			item_data = EXCLUDED.item_data,
			item_lifetime = EXCLUDED.item_lifetime,
			item_time = EXCLUDED.item_time
	`, itemID, value, lifetime, c.now().Unix())
	return err
}

func (c *SharedCache) deleteFromDatabase(ctx context.Context, key string) error {
	ctx, cancel := context.WithTimeout(ctx, sharedCacheDatabaseTimeout)
	defer cancel()
	db := c.database()
	if db == nil {
		return nil
	}
	itemID, err := sharedCacheItemID(key)
	if err != nil {
		return err
	}

	_, err = db.ExecContext(ctx, `DELETE FROM cache_items WHERE item_id = $1`, itemID)
	return err
}
