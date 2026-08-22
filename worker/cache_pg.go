package main

import (
	"context"
	"database/sql"
	"errors"
	"time"
)

// cachePgGet reads a row from cache_items. A found-but-expired row is
// deleted opportunistically and reported as not found. expiresAt is the
// zero time.Time when the row has no lifetime set (never expires).
func cachePgGet(ctx context.Context, db *sql.DB, key string) (data []byte, expiresAt time.Time, found bool, err error) {

	var lifetime sql.NullInt64
	var storedAt int64

	err = db.QueryRowContext(ctx, `
		SELECT item_data, item_lifetime, item_time
		FROM cache_items
		WHERE item_id = $1
	`, key).Scan(&data, &lifetime, &storedAt)

	if errors.Is(err, sql.ErrNoRows) {
		return nil, time.Time{}, false, nil
	}

	if err != nil {
		return nil, time.Time{}, false, err
	}

	if lifetime.Valid {
		expiresAt = time.Unix(storedAt+lifetime.Int64, 0)
		if expiresAt.Before(time.Now()) {
			_, _ = db.ExecContext(ctx, `DELETE FROM cache_items WHERE item_id = $1`, key)
			return nil, time.Time{}, false, nil
		}
	}

	return data, expiresAt, true, nil

}

// cachePgSet upserts a row into cache_items.
func cachePgSet(ctx context.Context, db *sql.DB, key string, data []byte, ttl time.Duration) error {

	_, err := db.ExecContext(ctx, `
		INSERT INTO cache_items (item_id, item_data, item_lifetime, item_time)
		VALUES ($1, $2, $3, $4)
		ON CONFLICT (item_id) DO UPDATE SET
			item_data = EXCLUDED.item_data,
			item_lifetime = EXCLUDED.item_lifetime,
			item_time = EXCLUDED.item_time
	`, key, data, int(ttl.Seconds()), time.Now().Unix())

	return err

}
