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
		}

		slog.Info(bounceUuid) // TODO: remove this

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

func (m *IncomingMail) handleBounce(uuid string) (*bounceparse.Dsn, error) {

	dsn, err := bounceparse.ParseDsn([]byte(m.Data))

	if err != nil {
		slog.Error("Error parsing bounce email", "error", err)
		return nil, err
	}

	return dsn, nil

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
