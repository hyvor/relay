package main

import (
	"context"
	"crypto/tls"
	"errors"
	"io"
	"log/slog"
	"net"
	"net/mail"
	"os"
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
	ctx context.Context
	metrics *Metrics
	logger         *slog.Logger
	instanceDomain string
	mailChannel    chan *IncomingMail
	security       GoStateSecurity
	systemApiKey   string // api key for unauthenticated SMTP sends
}

// A Session is returned after successful login.
type Session struct {
	ctx          context.Context
	logger       *slog.Logger
	metrics      *Metrics
	incomingMail IncomingMail
	mailChannel  chan *IncomingMail
	security     GoStateSecurity
	remoteIP     string
	systemApiKey string // api key for unauthenticated SMTP sends
}

// NewSession is called after client greeting (EHLO, HELO).
func (bkd *IncomingBackend) NewSession(c *smtp.Conn) (smtp.Session, error) {

	remoteIP := remoteIPFromConn(c)

	// Anti-open-relay: source-IP allowlist (skip when empty = no policy).
	if len(bkd.security.AllowedSourceIPs) > 0 && !ipInAllowlist(remoteIP, bkd.security.AllowedSourceIPs) {
		bkd.logger.Warn("Rejected connection: source IP not allowed", "remote_ip", remoteIP)
		return nil, errors.New("source IP not allowed")
	}

	return &Session{
		ctx:          bkd.ctx,
		logger:      bkd.logger,
		metrics: bkd.metrics,
		mailChannel: bkd.mailChannel,
		security:    bkd.security,
		remoteIP:    remoteIP,
		systemApiKey: bkd.systemApiKey,
		incomingMail: IncomingMail{
			InstanceDomain: bkd.instanceDomain,
		},
	}, nil
}

func remoteIPFromConn(c *smtp.Conn) string {
	if c == nil {
		return ""
	}
	conn := c.Conn()
	if conn == nil {
		return ""
	}
	host, _, err := net.SplitHostPort(conn.RemoteAddr().String())
	if err != nil {
		return conn.RemoteAddr().String()
	}
	return host
}

func ipInAllowlist(ip string, allowed []string) bool {
	if ip == "" {
		return false
	}
	parsed := net.ParseIP(ip)
	for _, entry := range allowed {
		entry = strings.TrimSpace(entry)
		if entry == "" {
			continue
		}
		if entry == ip {
			return true
		}
		if _, cidr, err := net.ParseCIDR(entry); err == nil && parsed != nil && cidr.Contains(parsed) {
			return true
		}
	}
	return false
}

func domainInAllowlist(domain string, allowed []string) bool {
	domain = strings.ToLower(strings.TrimSpace(domain))
	for _, entry := range allowed {
		if strings.EqualFold(strings.TrimSpace(entry), domain) {
			return true
		}
	}
	return false
}

// AuthMechanisms returns a slice of available auth mechanisms; only PLAIN is
// supported in this example.
func (s *Session) AuthMechanisms() []string {
	return []string{sasl.Plain}
}

// SmtpAuthRequest/Response — Symfony local /api/local/auth/smtp contract.
type SmtpAuthRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
	RemoteIP string `json:"remoteIp,omitempty"`
}

type SmtpAuthResponse struct {
	Authenticated bool   `json:"authenticated"`
	ApiKey        string `json:"apiKey,omitempty"`
	Reason        string `json:"reason,omitempty"`
}

// Auth is the handler for supported authenticators.
// When SmtpAuthViaSymfony is enabled the credentials are delegated to the
// Symfony backend (which may, in turn, validate against Active Directory/LDAP).
// Otherwise the legacy behavior applies: password is treated as an API key.
func (s *Session) Auth(mech string) (sasl.Server, error) {
	return sasl.NewPlainServer(func(identity, username, password string) error {

		if s.security.SmtpAuthViaSymfony {
			var resp SmtpAuthResponse
			err := CallLocalApi(s.ctx, "POST", "/auth/smtp", SmtpAuthRequest{
				Username: username,
				Password: password,
				RemoteIP: s.remoteIP,
			}, &resp)

			if err != nil {
				s.logger.Warn("SMTP AUTH delegation to Symfony failed", "error", err, "username", username)
				return errors.New("authentication failed")
			}

			if !resp.Authenticated {
				s.logger.Info("SMTP AUTH rejected by Symfony", "username", username, "reason", resp.Reason)
				return errors.New("authentication failed")
			}

			// Symfony returns the effective API key to use for /api/console/sends.
			s.incomingMail.ApiKey = resp.ApiKey
			return nil
		}

		s.incomingMail.ApiKey = password
		return nil
	}), nil

}

func (s *Session) Mail(from string, opts *smtp.MailOptions) error {

	// Sender-domain allowlist applies only to unauthenticated/non-API submissions.
	if !s.incomingMail.HasApiKey() && len(s.security.AllowedSenderDomains) > 0 {
		parsed, err := mail.ParseAddress(from)
		if err != nil {
			return errors.New("invalid sender address: " + err.Error())
		}
		at := strings.LastIndex(parsed.Address, "@")
		if at == -1 || at == len(parsed.Address)-1 {
			return errors.New("invalid sender address: missing domain part")
		}
		domain := parsed.Address[at+1:]
		if !domainInAllowlist(domain, s.security.AllowedSenderDomains) {
			s.logger.Warn("Rejected MAIL FROM: sender domain not allowed", "domain", domain, "remote_ip", s.remoteIP)
			return errors.New("sender domain not allowed")
		}
	}

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

		// when unauthenticated sending is allowed, accept any domain
		if s.security.AllowUnauthenticatedSending && domain != s.incomingMail.InstanceDomain {
			s.logger.Debug("Unauthenticated SMTP send accepted",
				"domain", domain, "source_ip", s.remoteIP)
		} else if domain != s.incomingMail.InstanceDomain {
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

	if s.incomingMail.HasApiKey() {
		return forwardEmailToApi(s.ctx, s.logger, s.metrics, &s.incomingMail)
	}

	// unauthenticated send — use system API key if feature is enabled
	if s.security.AllowUnauthenticatedSending && s.systemApiKey != "" {
		// check if this is a send (not bounce/FBL to instance domain)
		atIndex := strings.LastIndex(s.incomingMail.RcptTo, "@")
		if atIndex > 0 && atIndex < len(s.incomingMail.RcptTo)-1 {
			domain := s.incomingMail.RcptTo[atIndex+1:]
			if domain != s.incomingMail.InstanceDomain {
				s.incomingMail.ApiKey = s.systemApiKey
				return forwardEmailToApi(s.ctx, s.logger, s.metrics, &s.incomingMail)
			}
		}
	} else if s.security.AllowUnauthenticatedSending && s.systemApiKey == "" {
		s.logger.Warn("UNAUTHENTICATED_SEND_API_KEY not set — unauthenticated SMTP send dropped",
			"MAIL", s.incomingMail.MailFrom, "RCPT", s.incomingMail.RcptTo, "source_ip", s.remoteIP)
	}

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

func (server *IncomingMailServer) Set(instanceDomain string, numWorkers int, mailTls GoStateMailTls, security GoStateSecurity) {
	server.Shutdown()
	server.StartChannelAndSmtpServers(instanceDomain, numWorkers, mailTls, security)

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

func (server *IncomingMailServer) StartChannelAndSmtpServers(instanceDomain string, numWorkers int, mailTls GoStateMailTls, security GoStateSecurity) {

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

	go server.StartSmtpServer(smtpServerPort1, instanceDomain, mailTls, security, mailChannel, 1)
	go server.StartSmtpServer(smtpServerPort2, instanceDomain, mailTls, security, mailChannel, 2)

}

func (server *IncomingMailServer) StartSmtpServer(
	port string,
	instanceDomain string,
	mailTls GoStateMailTls,
	security GoStateSecurity,
	mailChannel chan *IncomingMail,
	serverNumber int,
) {

	be := &IncomingBackend{
		ctx: server.ctx,
		logger:         server.logger,
		instanceDomain: instanceDomain,
		mailChannel:    mailChannel,
		metrics: server.metrics,
		security: security,
		systemApiKey: os.Getenv("UNAUTHENTICATED_SEND_API_KEY"),
	}

	smtpServer := smtp.NewServer(be)

	smtpServer.Addr = "0.0.0.0" + port
	smtpServer.Domain = instanceDomain
	smtpServer.WriteTimeout = 60 * time.Second
	smtpServer.ReadTimeout = 60 * time.Second
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
			MinVersion:   tls.VersionTLS12,
			Certificates: []tls.Certificate{cert},
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
