package main

import (
	"context"
	"database/sql"
	"fmt"

	_ "github.com/lib/pq"
)

func NewDbConn() (*sql.DB, error) {

	connStr := "host=hyvor-service-pgsql port=5432 user=postgres password=postgres dbname=hyvor_relay sslmode=disable"
	db, err := sql.Open("postgres", connStr)

	if err != nil {
		return nil, fmt.Errorf("failed to connect to database: %w", err)
	}

	err = db.Ping()
	if err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	return db, nil

}

type DbSend struct {
	Id       int
	From     string
	To       string
	RawEmail string
}

type DbSendBatch struct {
	tx  *sql.Tx
	ctx context.Context
}

func NewDbSendBatch(
	ctx context.Context,
	db *sql.DB,
) (*DbSendBatch, error) {

	tx, err := db.BeginTx(ctx, nil)

	if err != nil {
		return nil, fmt.Errorf("failed to begin transaction: %w", err)
	}

	return &DbSendBatch{
		tx:  tx,
		ctx: ctx,
	}, nil
}

func (b *DbSendBatch) FetchSends(queueId int) ([]DbSend, error) {

	rows, err := b.tx.QueryContext(b.ctx, `
		WITH ids AS MATERIALIZED (
			SELECT id, from_address, to_address, raw
			FROM sends
			WHERE status = 'queued' AND queue_id = $1 AND send_after < NOW()
			FOR UPDATE SKIP LOCKED
			LIMIT $2
		)
		UPDATE sends
		SET status = 'processing', updated_at = NOW()
		WHERE id = ANY(SELECT id FROM ids)
		RETURNING id, from_address, to_address, raw
    `, queueId, 10)

	if err != nil {
		return nil, err
	}

	sends := make([]DbSend, 0)

	for rows.Next() {
		var send DbSend

		if err := rows.Scan(
			&send.Id,
			&send.From,
			&send.To,
			&send.RawEmail,
		); err != nil {
			return nil, err
		}

		sends = append(sends, send)
	}

	if err := rows.Close(); err != nil {
		return nil, err
	}

	return sends, nil

}

func (b *DbSendBatch) MarkSendAsSent(sendId int) error {
	_, err := b.tx.ExecContext(b.ctx, `
		UPDATE sends
		SET status = 'sent', updated_at = NOW()
		WHERE id = $1
	`, sendId)

	if err != nil {
		return fmt.Errorf("failed to mark send ID %d as sent: %w", sendId, err)
	}

	return nil
}

func (b *DbSendBatch) MarkSendAsFailed(sendId int, errorMessage string) error {
	_, err := b.tx.ExecContext(b.ctx, `
		UPDATE sends
		SET status = 'failed', error_message = $2, updated_at = NOW()
		WHERE id = $1
	`, sendId, errorMessage)

	if err != nil {
		return fmt.Errorf("failed to mark send ID %d as failed: %w", sendId, err)
	}

	return nil
}

func (b *DbSendBatch) RequeueSend(sendId int) error {
	// TODO: interval logic based on retry count
	_, err := b.tx.ExecContext(b.ctx, `
		UPDATE sends
		SET status = 'queued', send_after = NOW() + INTERVAL '15 minutes'
		WHERE id = $1
	`, sendId)

	if err != nil {
		return fmt.Errorf("failed to requeue send ID %d: %w", sendId, err)
	}

	return nil
}

func (b *DbSendBatch) Commit() error {
	if err := b.tx.Commit(); err != nil {
		return fmt.Errorf("failed to commit transaction: %w", err)
	}
	return nil
}

func (b *DbSendBatch) Rollback() error {
	if err := b.tx.Rollback(); err != nil {
		return fmt.Errorf("failed to rollback transaction: %w", err)
	}
	return nil
}
