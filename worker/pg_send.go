package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
)

type DbSend struct {
	Id       int
	From     string
	To       string
	RawEmail string
	TryCount int
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
			SELECT id, from_address, to_address, raw, try_count
			FROM sends
			WHERE status = 'queued' AND queue_id = $1 AND send_after < NOW()
			FOR UPDATE SKIP LOCKED
			LIMIT $2
		)
		UPDATE sends
		SET status = 'processing', updated_at = NOW()
		WHERE id = ANY(SELECT id FROM ids)
		RETURNING id, from_address, to_address, raw, try_count
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
			&send.TryCount,
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

func (b *DbSendBatch) FinalizeSendBySendResult(
	send *DbSend,
	sendResult *SendResult,
) (int, error) {

	status := "sent"
	if sendResult.Error != nil {
		status = "failed"
	} else if sendResult.ShouldRequeue {
		status = "queued"
	}

	var err error
	var sendAfterInterval string

	if status == "queued" {
		sendAfterInterval = fmt.Sprintf("NOW() + INTERVAL '%s'", getSendAfterInterval(send.TryCount))
	} else {
		sendAfterInterval = "send_after"
	}

	// update send status
	_, err = b.tx.ExecContext(b.ctx, `
		UPDATE sends
		SET
			status = $1,
			updated_at = NOW(),
			try_count = try_count + 1,
			send_after = `+sendAfterInterval+`
		WHERE id = $2
	`, status, send.Id)

	if err != nil {
		return 0, fmt.Errorf("failed to update send ID %d status to %s: %w", send.Id, status, err)
	}

	// create send attempt
	var errorMessage sql.NullString
	if sendResult.Error != nil {
		errorMessage = sql.NullString{
			String: sendResult.Error.Error(),
			Valid:  true,
		}
	} else {
		errorMessage = sql.NullString{
			String: "",
			Valid:  false,
		}
	}

	var sentMxHost sql.NullString
	if sendResult.SentMxHost != "" {
		sentMxHost = sql.NullString{
			String: sendResult.SentMxHost,
			Valid:  true,
		}
	} else {
		sentMxHost = sql.NullString{
			String: "",
			Valid:  false,
		}
	}

	resolvedMxHosts, _ := json.Marshal(sendResult.ResolvedMxHosts)
	smtpConversations, _ := json.Marshal(sendResult.SmtpConversations)

	var attemptId int
	err = b.tx.QueryRowContext(b.ctx, `
		INSERT INTO send_attempts (
			created_at,
			updated_at,
			send_id,
			ip_address_id,
			status,
			try_count,
			resolved_mx_hosts,
			sent_mx_host,
			smtp_conversations,
			error
		)
		VALUES (
			NOW(),
			NOW(),
			$1,
			$2,
			$3,
			$4,
			$5,
			$6,
			$7,
			$8
		)
		RETURNING id
	`,
		send.Id,
		sendResult.SentFromIpId,
		status,
		send.TryCount+1,
		resolvedMxHosts,
		sentMxHost,
		smtpConversations,
		errorMessage,
	).Scan(&attemptId)

	if err != nil {
		return 0, fmt.Errorf("failed to insert send attempt for send ID %d: %w", send.Id, err)
	}

	return attemptId, nil

}

// tryCount is the number of attempts BEFORE this attempt
func getSendAfterInterval(tryCount int) string {

	if tryCount == 0 {
		return "15 minutes"
	}
	if tryCount == 1 {
		return "1 hour"
	}
	if tryCount == 2 {
		return "2 hours"
	}
	if tryCount == 3 {
		return "4 hours"
	}
	if tryCount == 4 {
		return "8 hours"
	}
	if tryCount == 5 {
		return "16 hours"
	}
	if tryCount == 6 {
		return "1 day"
	}

	return "2 days"
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
