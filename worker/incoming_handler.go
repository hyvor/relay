package main

import (
	"context"
	"log/slog"
	"strings"

	"github.com/hyvor/relay/worker/bounceparse"
	"github.com/jackc/pgx/v5/pgxpool"
)

type IncomingMail struct {
	MailFrom       string
	RcptTo         string
	Data           []byte
	InstanceDomain string
}

// call after the data is read
func (m *IncomingMail) Handle(pgpool *pgxpool.Pool) {

	isBounce, bounceUuid := checkBounceEmail(m.RcptTo, m.InstanceDomain)
	isFbl := checkFbl(m.RcptTo, m.InstanceDomain)

	if !isBounce && !isFbl {
		slog.Info(
			"Received email that is not a bounce or FBL",
			"MAIL", m.MailFrom,
			"RCPT", m.RcptTo,
		)
		return
	}

	var debugType DebugIncomingType
	var debugStatus DebugIncomingStatus
	var debugErrorMessage string
	var debugParsedData interface{}

	if isBounce {

		debugType = DebugIncomingTypeBounce

		bounceDsn, err := bounceparse.ParseDsn(m.Data)

		debugStatus = DebugIncomingStatusSuccess
		debugErrorMessage = ""
		if err != nil {
			debugStatus = DebugIncomingStatusFailed
			debugErrorMessage = err.Error()
		} else {

			debugParsedData = bounceDsn
			m.finalizeBounce(bounceDsn, bounceUuid, pgpool)

		}

	} else if isFbl {

		arf, err := bounceparse.ParseArf(m.Data)

		debugType = DebugIncomingTypeFbl
		debugStatus = DebugIncomingStatusSuccess
		debugErrorMessage = ""
		if err != nil {
			debugStatus = DebugIncomingStatusFailed
			debugErrorMessage = err.Error()
		} else {
			debugParsedData = arf
			m.finalizeFbl(arf, pgpool)
		}

	}

	createDebugRecord(
		pgpool,
		debugType,
		debugStatus,
		m.Data,
		m.MailFrom,
		m.RcptTo,
		debugParsedData,
		debugErrorMessage,
	)

}

func (m *IncomingMail) finalizeBounce(
	bounceDsn *bounceparse.Dsn,
	bounceUuid string,
	pgpool *pgxpool.Pool,
) {

	// since each email generates a new UUID, we can safely
	// assume that only one reciepient is present in the DSN
	if len(bounceDsn.Recipients) == 0 {
		slog.Error("Received bounce with no recipients", "UUID", bounceUuid)
		return
	}

	recipient := bounceDsn.Recipients[0]

	// we are not interested in delayed or delivered actions
	// most email clients do not even send delivered reports
	if recipient.Action != "failed" {
		slog.Info(
			"Received bounce with non-failed action",
			"UUID", bounceUuid,
			"ACTION", recipient.Action,
		)
		return
	}

	// We are only interested in bounces that have a status code that starts with 5
	// (permanent failures)
	if recipient.Status[0] != 5 {
		slog.Info(
			"Received bounce with non-permanent status code",
			"UUID", bounceUuid,
			"STATUS", recipient.Status,
		)
		return
	}

	send, err := getSendByUuid(pgpool, bounceUuid)

	if err != nil {
		slog.Error("Failed to get send by UUID", "UUID", bounceUuid, "error", err)
		return
	}

	createSuppression(pgpool, send.ProjectId, send.To, "bounce", bounceDsn.ReadableText)

}

func (m *IncomingMail) finalizeFbl(
	arf *bounceparse.Arf,
	pgpool *pgxpool.Pool,
) {

	parts := strings.Split(arf.MessageId, "@")

	if len(parts) < 2 {
		slog.Error("Received FBL with invalid Message-ID", "Message-ID", arf.MessageId)
		return
	}

	uuid := parts[0]

	send, err := getSendByUuid(pgpool, uuid)

	if err != nil {
		slog.Error("Failed to get send by UUID", "UUID", uuid, "error", err)
		return
	}

	createSuppression(pgpool, send.ProjectId, send.To, "fbl", arf.ReadableText)

}

// Bounce email format: bounce+<uuid>@<instance_domain>
func checkBounceEmail(rcptTo string, instanceDomain string) (bool, string) {

	if rcptTo == "" {
		return false, ""
	}

	at := strings.Index(rcptTo, "@")

	if at < 0 {
		return false, ""
	}

	if !strings.HasPrefix(rcptTo[:at], "bounce+") {
		return false, ""
	}

	if !strings.HasSuffix(rcptTo[at:], instanceDomain) {
		return false, ""
	}

	return true, rcptTo[7:at]

}

// fbl email is: fbl@<instance_domain>
func checkFbl(rcptTo string, instanceDomain string) bool {

	if rcptTo == "" {
		return false
	}

	at := strings.Index(rcptTo, "@")

	if at < 0 {
		return false
	}

	if !strings.HasPrefix(rcptTo[:at], "fbl") {
		return false
	}

	if !strings.HasSuffix(rcptTo[at:], instanceDomain) {
		return false
	}

	return true

}

func incomingMailWorker(
	ctx context.Context,
	mailChannel chan *IncomingMail,
	pgpool *pgxpool.Pool,
) {

	slog.Info("Starting incoming mail handler")

	for {
		select {
		case <-ctx.Done():
			return
		case mail := <-mailChannel:
			if mail == nil {
				continue
			}
			mail.Handle(pgpool)
		}
	}

}
