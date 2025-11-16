package main

import (
	"context"
	"log/slog"
	"strings"

	"github.com/hyvor/relay/worker/bounceparse"
	"github.com/hyvor/relay/worker/smtp_interface"
)

type IncomingMailType string

const (
	IncomingMailTypeBounce IncomingMailType = "bounce"
	IncomingMailTypeFbl    IncomingMailType = "fbl"
)

type IncomingMail struct {
	MailFrom       string
	RcptTo         string
	Data           []byte
	InstanceDomain string
	ApiKey string // using the auth password
}

func (m *IncomingMail) HasApiKey() bool {
	return m.ApiKey != ""
}

// call after the data is read
func (m *IncomingMail) Handle(ctx context.Context, logger *slog.Logger, metrics *Metrics) {


	if m.HasApiKey() {
		forwardEmailToApi(ctx, logger, metrics, m)
		return
	}

	isBounce, bounceUuid := checkBounce(m.RcptTo, m.InstanceDomain)
	isFbl := checkFbl(m.RcptTo, m.InstanceDomain)

	if !isBounce && !isFbl {
		metrics.incomingEmailsTotal.WithLabelValues("unknown").Inc()

		logger.Info(
			"Received email that is not a bounce or FBL",
			"MAIL", m.MailFrom,
			"RCPT", m.RcptTo,
		)
		return
	}

	payload := make(map[string]interface{})
	var debugType IncomingMailType

	if isBounce {
		debugType = IncomingMailTypeBounce
		bounceDsn, err := bounceparse.ParseDsn(m.Data)

		if err != nil {
			payload["error"] = err.Error()
		} else {
			payload["dsn"] = bounceDsn
			payload["bounce_uuid"] = bounceUuid
		}
	} else if isFbl {
		debugType = IncomingMailTypeFbl
		arf, err := bounceparse.ParseArf(m.Data)

		if err != nil {
			payload["error"] = err.Error()
		} else {
			payload["arf"] = arf
		}
	}

	metrics.incomingEmailsTotal.WithLabelValues(string(debugType)).Inc()

	payload["type"] = debugType
	payload["raw_email"] = string(m.Data)
	payload["mail_from"] = m.MailFrom
	payload["rcpt_to"] = m.RcptTo

	CallLocalApi(ctx, "POST", "/incoming", payload, nil)

}

// Bounce email format: bounce+<uuid>@<instance_domain>
func checkBounce(rcptTo string, instanceDomain string) (bool, string) {

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

	if rcptTo[:at] != "fbl" {
		return false
	}

	if rcptTo[at:] != "@"+instanceDomain {
		return false
	}

	return true

}

func incomingMailWorker(
	ctx context.Context,
	i int,
	logger *slog.Logger,
	metrics *Metrics,
	mailChannel chan *IncomingMail,
) {

	logger.Debug("Starting incoming mail handler", "worker", i)

	for {
		select {
		case <-ctx.Done():
			return
		case mail := <-mailChannel:
			if mail == nil {
				continue
			}
			mail.Handle(ctx, logger, metrics)
		}
	}

}

func forwardEmailToApi(
	ctx context.Context,
	logger *slog.Logger,
	metrics *Metrics,
	m *IncomingMail,
) {

	payload, err := smtp_interface.MimeToApiRequest(m.Data)

	if err != nil {
		logger.Error("Unable to convert mime to API request", "error", err.Error())
		return
	}

	err = CallConsoleSendApi(
		ctx,
		m.ApiKey,
		payload,
	)

	if err != nil {
		logger.Error("Unable to forward incoming email to API", "error", err.Error())
		return
	}

	metrics.incomingEmailsTotal.WithLabelValues("send").Inc()

	logger.Debug(
		"Forwarded incoming sending email to API",
		"MAIL", m.MailFrom,
		"RCPT", m.RcptTo,
	)

}