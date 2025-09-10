package main

import (
	"context"
	"errors"
	"io"
	"log/slog"
	"net/mail"
	"strings"
	"time"

	"github.com/emersion/go-sasl"
	smtp "github.com/emersion/go-smtp"
)

// Inconming server is a simple SMTP server that handles incoming emails to the instance domain emails.
// It handles:
// 1. Bounce emails: emails sent to bounce+<uuid>@<instance_domain>. (DSN format, RFC 3464)
// 2. Feedback loop emails: emails sent to feedback+<uuid>@<instance_domain>. (ARF format, RFC 5965).

// The IncomingBackend implements SMTP server methods.
type IncomingBackend struct {
	logger         *slog.Logger
	instanceDomain string
	mailChannel    chan *IncomingMail
}

// NewSession is called after client greeting (EHLO, HELO).
func (bkd *IncomingBackend) NewSession(c *smtp.Conn) (smtp.Session, error) {
	return &Session{
		logger:      bkd.logger,
		mailChannel: bkd.mailChannel,
		incomingMail: IncomingMail{
			InstanceDomain: bkd.instanceDomain,
		},
	}, nil
}

// A Session is returned after successful login.
type Session struct {
	logger       *slog.Logger
	incomingMail IncomingMail
	mailChannel  chan *IncomingMail
}

// AuthMechanisms returns a slice of available auth mechanisms; only PLAIN is
// supported in this example.
func (s *Session) AuthMechanisms() []string {
	return []string{sasl.Plain}
}

// Auth is the handler for supported authenticators.
func (s *Session) Auth(mech string) (sasl.Server, error) {
	return nil, errors.New("authentication not supported")
}

func (s *Session) Mail(from string, opts *smtp.MailOptions) error {
	s.incomingMail.MailFrom = from
	return nil
}

func (s *Session) Rcpt(to string, opts *smtp.RcptOptions) error {
	parsed, err := mail.ParseAddress(to)

	if err != nil {
		return errors.New("recipient address is invalid: " + err.Error())
	}

	atIndex := strings.LastIndex(parsed.Address, "@")
	if atIndex == -1 || atIndex == len(parsed.Address)-1 {
		return errors.New("recipient address is invalid: missing domain part")
	}

	domain := parsed.Address[atIndex+1:]

	if domain != s.incomingMail.InstanceDomain {
		return errors.New("this SMTP server only accepts emails for " + s.incomingMail.InstanceDomain)
	}

	s.incomingMail.RcptTo = parsed.Address
	return nil
}

func (s *Session) Data(r io.Reader) error {
	if b, err := io.ReadAll(r); err != nil {
		s.logger.Error("Error reading email data", "error", err)
		return err
	} else {

		s.logger.Info("Received email",
			"MAIL", s.incomingMail.MailFrom,
			"RCPT", s.incomingMail.RcptTo,
			"data", string(b),
		)

		s.incomingMail.Data = b
		s.mailChannel <- &s.incomingMail
	}
	return nil
}

func (s *Session) Reset() {}

func (s *Session) Logout() error {
	return nil
}

type IncomingMailServer struct {
	ctx     context.Context
	logger  *slog.Logger
	metrics *Metrics

	smtpServer *smtp.Server
	cancelFunc context.CancelFunc
}

func NewIncomingMailServer(ctx context.Context, logger *slog.Logger, metrics *Metrics) *IncomingMailServer {
	return &IncomingMailServer{
		ctx:     ctx,
		logger:  logger.With("component", "incoming_mail_server"),
		metrics: metrics,
	}
}

func (server *IncomingMailServer) Set(instanceDomain string, numWorkers int) {
	server.Shutdown()

	go func() {
		server.Start(instanceDomain, numWorkers)
	}()

	go func() {
		<-server.ctx.Done()
		server.Shutdown()
	}()
}

func (server *IncomingMailServer) Shutdown() {
	if server.smtpServer == nil {
		return
	}

	if server.cancelFunc != nil {
		server.cancelFunc()
		server.cancelFunc = nil
	}

	shutdownCtx, shutdownCtxCancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer shutdownCtxCancel()

	if err := server.smtpServer.Shutdown(shutdownCtx); err != nil {
		server.logger.Error("Failed to shutdown SMTP server", "error", err)
	}

	server.smtpServer = nil

}

var smtpServerPort = ":25"

func (server *IncomingMailServer) Start(instanceDomain string, numWorkers int) {

	// channel
	mailChannel := make(chan *IncomingMail)

	// worker context
	workerCtx, cancel := context.WithCancel(server.ctx)
	server.cancelFunc = cancel
	defer cancel()

	be := &IncomingBackend{
		logger:         server.logger,
		instanceDomain: instanceDomain,
		mailChannel:    mailChannel,
	}

	for i := 0; i < numWorkers; i++ {
		go incomingMailWorker(
			workerCtx,
			i,
			server.logger,
			server.metrics,
			mailChannel,
		)
	}

	smtpServer := smtp.NewServer(be)

	smtpServer.Addr = "0.0.0.0" + smtpServerPort
	smtpServer.Domain = instanceDomain
	smtpServer.WriteTimeout = 10 * time.Second
	smtpServer.ReadTimeout = 10 * time.Second
	smtpServer.MaxMessageBytes = 1024 * 1024
	smtpServer.MaxRecipients = 10
	smtpServer.AllowInsecureAuth = true

	server.smtpServer = smtpServer

	server.logger.Info("Starting incoming mail server at " + smtpServer.Addr)
	if err := smtpServer.ListenAndServe(); err != nil {
		server.logger.Error("Failed to start incoming mail server", "error", err)
	}
}
