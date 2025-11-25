package main

import (
	"context"
	"crypto/tls"
	"errors"
	"io"
	"log/slog"
	"net/mail"
	"strings"
	"time"

	"github.com/emersion/go-sasl"
	smtp "github.com/emersion/go-smtp"
)

// Incoming server is a simple SMTP server that handles incoming emails to the instance domain emails.
// It handles:
// 1. Bounce emails: emails sent to bounce+<uuid>@<instance_domain>. (DSN format, RFC 3464)
// 2. Feedback loop emails: emails sent to fbl@<instance_domain> or abuse@<instance_domain>. (ARF format, RFC 5965).
// 3. Forward emails to the API when AUTH is used; the password is treated as the API key, and the email is forwarded to the API.

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
	return sasl.NewPlainServer(func(identity, username, password string) error {
		s.incomingMail.ApiKey = password
		return nil
	}), nil

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

	if !s.incomingMail.HasApiKey() {

		// verify that the domain is the instance domain
		// if AUTH is used, skip this check (forward to API)

		atIndex := strings.LastIndex(parsed.Address, "@")
		if atIndex == -1 || atIndex == len(parsed.Address)-1 {
			return errors.New("recipient address is invalid: missing domain part")
		}

		domain := parsed.Address[atIndex+1:]

		if domain != s.incomingMail.InstanceDomain {
			return errors.New("this SMTP server only accepts emails for " + s.incomingMail.InstanceDomain)
		}

	}
	
	s.incomingMail.RcptTo = parsed.Address

	return nil
}

func (s *Session) Data(r io.Reader) error {
	b, err := io.ReadAll(r)
	if err != nil {
		s.logger.Error("Error reading email data", "error", err)
		return err
	}

	s.logger.Debug("Received email",
		"MAIL", s.incomingMail.MailFrom,
		"RCPT", s.incomingMail.RcptTo,
		"data", string(b),
	)

	s.incomingMail.Data = b
	s.mailChannel <- &s.incomingMail
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

	smtpServer1 *smtp.Server // 25
	smtpServer2 *smtp.Server // 587

	workersCancelFunc context.CancelFunc
}

func NewIncomingMailServer(ctx context.Context, logger *slog.Logger, metrics *Metrics) *IncomingMailServer {
	return &IncomingMailServer{
		ctx:     ctx,
		logger:  logger.With("component", "incoming_mail_server"),
		metrics: metrics,
	}
}

func (server *IncomingMailServer) Set(instanceDomain string, numWorkers int, mailTls GoStateMailTls) {
	server.Shutdown()
	server.StartChannelAndSmtpServers(instanceDomain, numWorkers, mailTls)

	go func() {
		<-server.ctx.Done()
		server.Shutdown()
	}()
}

func (server *IncomingMailServer) Shutdown() {

	if server.workersCancelFunc != nil {
		server.workersCancelFunc()
		server.workersCancelFunc = nil
	}

	shutdownCtx, shutdownCtxCancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer shutdownCtxCancel()

	if server.smtpServer1 != nil {
		err := server.smtpServer1.Shutdown(shutdownCtx)
		if err != nil {
			server.logger.Error("Failed to shutdown SMTP server", "error", err)
		}
		server.smtpServer1 = nil
	}

	if server.smtpServer2 != nil {
		err := server.smtpServer2.Shutdown(shutdownCtx)
		if err != nil {
			server.logger.Error("Failed to shutdown SMTP server", "error", err)
		}
		server.smtpServer2 = nil
	}

}

var smtpServerPort1 = ":25"
var smtpServerPort2 = ":587"

func (server *IncomingMailServer) StartChannelAndSmtpServers(instanceDomain string, numWorkers int, mailTls GoStateMailTls) {

	// channel
	mailChannel := make(chan *IncomingMail)

	// worker context
	workerCtx, cancel := context.WithCancel(server.ctx)
	server.workersCancelFunc = cancel

	for i := 0; i < numWorkers; i++ {
		go incomingMailWorker(
			workerCtx,
			i,
			server.logger,
			server.metrics,
			mailChannel,
		)
	}

	go server.StartSmtpServer(smtpServerPort1, instanceDomain, mailTls, mailChannel, 1)
	go server.StartSmtpServer(smtpServerPort2, instanceDomain, mailTls, mailChannel, 2)

}

func (server *IncomingMailServer) StartSmtpServer(
	port string,
	instanceDomain string,
	mailTls GoStateMailTls,
	mailChannel chan *IncomingMail,
	serverNumber int,
) {

	be := &IncomingBackend{
		logger:         server.logger,
		instanceDomain: instanceDomain,
		mailChannel:    mailChannel,
	}

	smtpServer := smtp.NewServer(be)

	smtpServer.Addr = "0.0.0.0" + port
	smtpServer.Domain = instanceDomain
	smtpServer.WriteTimeout = 10 * time.Second
	smtpServer.ReadTimeout = 10 * time.Second
	smtpServer.MaxMessageBytes = 1024 * 1024
	smtpServer.MaxRecipients = 10
	smtpServer.AllowInsecureAuth = true

	if mailTls.Enabled {
		cert, err := tls.X509KeyPair([]byte(mailTls.Certificate), []byte(mailTls.PrivateKey))

		if err != nil {
			server.logger.Info("cert", "certificate", mailTls.Certificate)
			server.logger.Info("cert", "privateKey", mailTls.PrivateKey)
			server.logger.Error("Failed to load TLS certificate for incoming mail server", "error", err)
			return
		}

		smtpServer.TLSConfig = &tls.Config{
			MinVersion: tls.VersionTLS12,
			Certificates:  []tls.Certificate{cert},
		}
	}

	if serverNumber == 1 {
		server.smtpServer1 = smtpServer
	} else if serverNumber == 2 {
		server.smtpServer2 = smtpServer
	}

	server.logger.Info("Starting incoming mail server at " + smtpServer.Addr)
	if err := smtpServer.ListenAndServe(); err != nil {
		server.logger.Error("Failed to start incoming mail server", "error", err)
	}

}