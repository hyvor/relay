package main

import (
	"context"
	"log/slog"

	"github.com/jackc/pgx/v5/pgxpool"
)

type DebugIncomingType string

const (
	DebugIncomingTypeBounce DebugIncomingType = "bounce"
	DebugIncomingTypeFbl    DebugIncomingType = "fbl"
)

type DebugIncomingStatus string

const (
	DebugIncomingStatusFailed  DebugIncomingStatus = "failed"
	DebugIncomingStatusSuccess DebugIncomingStatus = "success"
)

func createDebugRecord(
	pgpool *pgxpool.Pool,
	debugType DebugIncomingType,
	status DebugIncomingStatus,
	rawEmail []byte,
	mailFrom string,
	rcptTo string,
	parsedData interface{},
	errorMessage string,
) {

	_, err := pgpool.Exec(context.Background(), `
		INSERT INTO debug_incoming_emails (
			created_at, updated_at,
			type, status, raw_email, mail_from, rcpt_to, parsed_data, error_message
		) VALUES (NOW(), NOW(), $1, $2, $3, $4, $5, $6, $7)
	`, debugType, status, rawEmail, mailFrom, rcptTo, parsedData, errorMessage)

	if err != nil {
		slog.Error("Failed to create debug record", "error", err)
		return
	}

}
