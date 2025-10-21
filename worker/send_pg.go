package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
)

type SendRow struct {
	Id        int
	Uuid      string
	From      string
	RawEmail  string
	QueueName string
}

type RecipientRow struct {
	Id       int
	Type     string // "to", "cc", "bcc"
	Address  string
	TryCount int
}

type SendTransaction struct {
	tx  *sql.Tx
	ctx context.Context
}

func NewSendTransaction(
	ctx context.Context,
	db *sql.DB,
) (*SendTransaction, error) {

	tx, err := db.BeginTx(ctx, nil)

	if err != nil {
		return nil, fmt.Errorf("failed to begin transaction: %w", err)
	}

	return &SendTransaction{
		tx:  tx,
		ctx: ctx,
	}, nil
}

func (b *SendTransaction) FetchSend(queueId int) (*SendRow, []*RecipientRow, error) {

	row := b.tx.QueryRowContext(b.ctx, `
		WITH ids AS MATERIALIZED (
			SELECT id, uuid, from_address, raw, queue_name
			FROM sends
			WHERE queued = true AND queue_id = $1 AND send_after < NOW()
			FOR UPDATE SKIP LOCKED
			LIMIT 1
		)
		UPDATE sends
		SET queued = false, updated_at = NOW()
		WHERE id = ANY(SELECT id FROM ids)
		RETURNING id, uuid, from_address, raw, queue_name
    `, queueId)

	var send SendRow

	if err := row.Scan(
		&send.Id,
		&send.Uuid,
		&send.From,
		&send.RawEmail,
		&send.QueueName,
	); err != nil {
		return nil, nil, err
	}

	// Fetch recipients for the send
	rows, err := b.tx.QueryContext(b.ctx, `
		SELECT id, type, address, status, try_count
		FROM send_recipients
		WHERE send_id = $1
	`, send.Id)

	if err != nil {
		return nil, nil, fmt.Errorf("failed to fetch recipients for send ID %d: %w", send.Id, err)
	}

	defer rows.Close()

	var recipients []*RecipientRow
	for rows.Next() {
		var recipient RecipientRow
		var status string

		if err := rows.Scan(
			&recipient.Id,
			&recipient.Type,
			&recipient.Address,
			&status,
			&recipient.TryCount,
		); err != nil {
			return nil, nil, fmt.Errorf("failed to scan recipient: %w", err)
		}

		if status == "queued" || status == "deferred" {
			recipients = append(recipients, &recipient)
		}
	}

	return &send, recipients, nil

}

// if all accepted -> accepted
// if some accepted, some deferred/bounced -> partial
// otherwise -> deferred or bounced or failed (all same type)
func sendResultToAttemptStatus(result *SendResult) string {

	var hasAccepted = false
	var hasOther = false
	var hasOtherType RecipientStatusCode

	for _, rcptResult := range result.RcptResults {
		code := rcptResult.ToRecipientStatus()
		if code == RecipientStatusAccepted {
			hasAccepted = true
		} else {
			hasOther = true
			hasOtherType = code
		}
	}
	
	if hasAccepted {
		if hasOther {
			return "partial"
		} else {
			return "accepted"
		}
	}

	if hasOtherType == RecipientStatusDeferred {
		return "deferred"
	} else if hasOtherType == RecipientStatusBounced {
		return "bounced"
	} else {
		return "failed"
	}

}

func (b *SendTransaction) RecordAttempt(
	send *SendRow,
	recipients []*RecipientRow,
	sendResult *SendResult,
) (int, error) {

	var respondedMxHost sql.NullString
	if sendResult.RespondedMxHost != "" {
		respondedMxHost = sql.NullString{
			String: sendResult.RespondedMxHost,
			Valid:  true,
		}
	} else {
		respondedMxHost = sql.NullString{
			Valid: false,
		}
	}

	resolvedMxHostsJson, _ := json.Marshal(sendResult.ResolvedMxHosts)
	smtpConversationsJson, _ := json.Marshal(sendResult.SmtpConversations)

	var attemptId int
	err := b.tx.QueryRowContext(b.ctx, `
		INSERT INTO send_attempts (
			created_at,
			updated_at,
			send_id,
			ip_address_id,
			status,
			try_count,
			domain,
			resolved_mx_hosts,
			responded_mx_host,
			smtp_conversations,
			duration_ms
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
			$8,
			$9
		)
		RETURNING id
	`,
		send.Id,
		sendResult.SentFromIpId,
		sendResultToAttemptStatus(sendResult),
		sendResult.NewTryCount,
		sendResult.Domain,
		resolvedMxHostsJson,
		respondedMxHost,
		smtpConversationsJson,
		sendResult.Duration.Milliseconds(),
	).Scan(&attemptId)

	if err != nil {
		return 0, fmt.Errorf("failed to insert send attempt for send ID %d: %w", send.Id, err)
	}

	for _, rcptResult := range sendResult.RcptResults {

		var enhancedCode sql.NullString

		if rcptResult.EnhancedCode != [3]int{0, 0, 0} {
			enhancedCode = sql.NullString{
				String: fmt.Sprintf("%d.%d.%d", rcptResult.EnhancedCode[0], rcptResult.EnhancedCode[1], rcptResult.EnhancedCode[2]),
				Valid:  true,
			}
		}

		// record current results in send_attempt_recipients
		_, err = b.tx.ExecContext(b.ctx, `
			INSERT INTO send_attempt_recipients (
				created_at,
				updated_at,
				send_attempt_id,
				send_recipient_id,
				recipient_status,
				smtp_code,
				smtp_enhanced_code,
				smtp_message
			)
			VALUES (
				NOW(),
				NOW(),
				$1,
				$2,
				$3,
				$4,
				$5,
				$6
			)
		`, attemptId, rcptResult.RecipientId, rcptResult.ToRecipientStatus().ToString(), rcptResult.Code, enhancedCode, rcptResult.Message)

		if err != nil {
			return 0, fmt.Errorf("failed to insert send attempt recipient for recipient ID %d: %w", rcptResult.RecipientId, err)
		}

		// update send_recipients to the latest state
		_, err = b.tx.ExecContext(b.ctx, `
			UPDATE send_recipients
			SET 
				status = $1,
				try_count = $2
			WHERE id = $3
		`, rcptResult.ToRecipientStatus().ToString(), sendResult.NewTryCount, rcptResult.RecipientId)

		if err != nil {
			return 0, fmt.Errorf("failed to update recipient ID %d status: %w", rcptResult.RecipientId, err)
		}

	}

	return attemptId, nil

}

func (b *SendTransaction) RequeueSend(sendId int, tryCount int) error {
	_, err := b.tx.ExecContext(b.ctx, `
		UPDATE sends
		SET 
			queued = true, 
			send_after = NOW() + INTERVAL '`+getSendAfterInterval(tryCount)+`',
			updated_at = NOW()
		WHERE id = $1
	`, sendId)

	if err != nil {
		return err
	}

	return nil
}

func (stx *SendTransaction) MarkSendAsDone(sendId int) error {

	// set queued to false
	_, err := stx.tx.ExecContext(stx.ctx, `
		UPDATE sends
		SET queued = false, updated_at = NOW()
		WHERE id = $1
	`, sendId)

	if err != nil {
		return err
	}

	return nil

}

func (b *SendTransaction) Commit() error {
	if err := b.tx.Commit(); err != nil {
		return fmt.Errorf("failed to commit transaction: %w", err)
	}
	return nil
}

func (b *SendTransaction) Rollback() error {
	if err := b.tx.Rollback(); err != nil {
		return fmt.Errorf("failed to rollback transaction: %w", err)
	}
	return nil
}
