package main

import (
	"context"
	"log/slog"

	uuidlib "github.com/google/uuid"
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
	logger *slog.Logger,
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
		logger.Error("Failed to create debug record", "error", err)
		return
	}

}

type SendByUuid struct {
	ProjectId int64
	To        string
}

func getSendByUuid(
	pgpool *pgxpool.Pool,
	uuid string,
) (*SendByUuid, error) {

	_, err := uuidlib.Parse(uuid)

	if err != nil {
		return nil, err
	}

	var send SendByUuid

	err = pgpool.QueryRow(context.Background(), `
		SELECT project_id, to_address FROM sends WHERE uuid = $1
	`, uuid).Scan(&send.ProjectId, &send.To)

	if err != nil {
		return nil, err
	}

	return &send, nil

}

func createSuppression(
	pgpool *pgxpool.Pool,
	projectId int64,
	email string,
	reason string,
	description string,
) error {

	_, err := pgpool.Exec(context.Background(), `
		INSERT INTO suppressions (
			created_at, updated_at, project_id, email, reason, description
		) VALUES (NOW(), NOW(), $1, $2, $3, $4)
	`, projectId, email, reason, description)

	if err != nil {
		return err
	}

	return nil
}
