package main

import (
	"context"
	"database/sql"
	"fmt"
	"time"
)

type WebhookDelivery struct {
	Id          int
	Url         string
	RequestBody string
	TryCount    int
	Signature   string
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
			SELECT id, url, request_body, try_count, signature
			FROM webhook_deliveries
			WHERE status = 'pending' AND send_after <= NOW()
			FOR UPDATE SKIP LOCKED
			LIMIT $1
		)
		UPDATE webhook_deliveries
		SET status = 'processing', updated_at = NOW()
		WHERE id = ANY(SELECT id FROM ids)
		RETURNING id, url, request_body, try_count, signature
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
			&delivery.Signature,
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

// getWebhookRetryDelay is the base backoff ladder for a failed webhook
// delivery, keyed by the try that just failed. Anything past the ladder waits
// a day.
func getWebhookRetryDelay(currentTry int) time.Duration {
	retryDelays := map[int]time.Duration{
		0: 1 * time.Minute,
		1: 5 * time.Minute,
		2: 15 * time.Minute,
		3: 1 * time.Hour,
		4: 4 * time.Hour,
		5: 24 * time.Hour,
	}

	delay, ok := retryDelays[currentTry]

	if !ok {
		delay = 24 * time.Hour
	}

	return delay
}

// getWebhookRetryInterval returns the SQL expression assigned to send_after.
//
// When there is nothing left to retry the column keeps its current value, so
// the expression is the column itself rather than an interval.
func getWebhookRetryInterval(currentTry int, currentSuccess bool) string {
	if currentSuccess || currentTry >= WEBHOOKS_MAX_RETRIES {
		return "send_after"
	}

	// jittered so that an endpoint that went down while a burst of deliveries
	// was queued does not get the whole burst back at the same instant
	return fmt.Sprintf("NOW() + INTERVAL '%s'", jitteredSqlInterval(getWebhookRetryDelay(currentTry)))
}
