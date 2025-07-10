package main

import (
	"context"
	"database/sql"
	"fmt"
)

type WebhookDelivery struct {
	Id          int
	Url         string
	RequestBody string
	TryCount    int
}

const WEBHOOKS_PER_BATCH = 10

type WebhooksBatch struct {
	tx  *sql.Tx
	ctx context.Context
}

func NewWebhooksBatch(
	ctx context.Context,
	db *sql.DB,
) (*WebhooksBatch, error) {

	tx, err := db.BeginTx(ctx, nil)

	if err != nil {
		return nil, fmt.Errorf("failed to begin transaction: %w", err)
	}

	return &WebhooksBatch{
		tx:  tx,
		ctx: ctx,
	}, nil
}

func (b *WebhooksBatch) FetchWebhooks() ([]WebhookDelivery, error) {

	rows, err := b.tx.QueryContext(b.ctx, `
		WITH ids AS MATERIALIZED (
			SELECT id, url, request_body, try_count
			FROM webhook_deliveries
			WHERE status = 'pending' AND send_after <= NOW()
			FOR UPDATE SKIP LOCKED
			LIMIT $1
		)
		UPDATE webhook_deliveries
		SET status = 'processing', updated_at = NOW()
		WHERE id = ANY(SELECT id FROM ids)
		RETURNING id, url, request_body, try_count
    `, WEBHOOKS_PER_BATCH)

	if err != nil {
		return nil, err
	}

	webhookDeliveries := make([]WebhookDelivery, 0)

	for rows.Next() {
		var delivery WebhookDelivery

		if err := rows.Scan(
			&delivery.Id,
			&delivery.Url,
			&delivery.RequestBody,
			&delivery.TryCount,
		); err != nil {
			return nil, err
		}

		webhookDeliveries = append(webhookDeliveries, delivery)
	}

	if err := rows.Close(); err != nil {
		return nil, err
	}

	return webhookDeliveries, nil

}

func (b *WebhooksBatch) Commit() error {
	if err := b.tx.Commit(); err != nil {
		return fmt.Errorf("failed to commit transaction: %w", err)
	}
	return nil
}

func (b *WebhooksBatch) Rollback() error {
	if err := b.tx.Rollback(); err != nil {
		return fmt.Errorf("failed to rollback transaction: %w", err)
	}
	return nil
}
