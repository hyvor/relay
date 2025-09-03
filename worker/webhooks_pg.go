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
const WEBHOOKS_MAX_RETRIES = 7

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
	if b.tx == nil {
		return nil
	}
	if err := b.tx.Rollback(); err != nil {
		return fmt.Errorf("failed to rollback transaction: %w", err)
	}
	return nil
}

func (b *WebhooksBatch) FinalizeWebhookByResult(delivery *WebhookDelivery, result *WebhookResult) error {

	// 1 for the first, 2 for the second, etc.
	currentTry := result.NewTryCount

	sendAfter := getWebhookRetryInterval(currentTry, result.Success)

	_, err := b.tx.ExecContext(b.ctx, `
		UPDATE webhook_deliveries
		SET 
			status = $1, 
			response = $2, 
			response_code = $3, 
			updated_at = NOW(),
			try_count = try_count + 1,
			send_after = `+sendAfter+`
		WHERE id = $4
	`,
		func() string {
			if result.Success {
				return "delivered"
			} else if currentTry >= WEBHOOKS_MAX_RETRIES {
				return "failed"
			} else {
				return "pending"
			}
		}(),
		result.ResponseBody,
		result.ResponseStatusCode,
		delivery.Id,
	)

	if err != nil {
		return fmt.Errorf("failed to finalize webhook delivery: %w", err)
	}

	return nil
}

func getWebhookRetryInterval(currentTry int, currentSuccess bool) string {
	if currentSuccess || currentTry >= WEBHOOKS_MAX_RETRIES {
		return "send_after"
	} else {
		retryIntervalMap := map[int]string{
			0: "1 minute",
			1: "5 minutes",
			2: "15 minutes",
			3: "1 hour",
			4: "4 hours",
			5: "24 hours",
		}
		interval, ok := retryIntervalMap[currentTry]

		if !ok {
			interval = "24 hours"
		}

		return fmt.Sprintf("NOW() + INTERVAL '%s'", interval)
	}
}
