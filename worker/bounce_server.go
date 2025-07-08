package main

import (
	"context"
	"errors"
	"io"
	"log"
	"log/slog"
	"time"

	"github.com/emersion/go-sasl"
	smtp "github.com/emersion/go-smtp"
)

// The BounceBackend implements SMTP server methods.
type BounceBackend struct{}

// NewSession is called after client greeting (EHLO, HELO).
func (bkd *BounceBackend) NewSession(c *smtp.Conn) (smtp.Session, error) {
	return &Session{}, nil
}

// A Session is returned after successful login.
type Session struct {
	auth bool
}

// AuthMechanisms returns a slice of available auth mechanisms; only PLAIN is
// supported in this example.
func (s *Session) AuthMechanisms() []string {
	return []string{sasl.Plain}
}

// Auth is the handler for supported authenticators.
func (s *Session) Auth(mech string) (sasl.Server, error) {
	return sasl.NewPlainServer(func(identity, username, password string) error {
		if username != "username" || password != "password" {
			return errors.New("Invalid username or password")
		}
		s.auth = true
		return nil
	}), nil
}

func (s *Session) Mail(from string, opts *smtp.MailOptions) error {
	log.Println("Mail from:", from)
	return nil
}

func (s *Session) Rcpt(to string, opts *smtp.RcptOptions) error {
	log.Println("Rcpt to:", to)
	return nil
}

func (s *Session) Data(r io.Reader) error {
	if b, err := io.ReadAll(r); err != nil {
		log.Println("Error reading data:", err)
		return err
	} else {
		log.Println("Received data:" + string(b))

		// _ := string(b[:])
	}
	return nil
}

func (s *Session) Reset() {}

func (s *Session) Logout() error {
	return nil
}

type BounceServer struct {
	ctx        context.Context
	logger     *slog.Logger
	smtpServer *smtp.Server
}

func NewBounceServer(ctx context.Context, logger *slog.Logger) *BounceServer {
	return &BounceServer{
		ctx:    ctx,
		logger: logger,
	}
}

func (b *BounceServer) Set() {
	b.logger.Info("Bounce server initializing...")

	b.Shutdown()

	go func() {
		b.Start(b.ctx, b.logger)
	}()

	go func() {
		<-b.ctx.Done()
		b.Shutdown()
	}()
}

func (b *BounceServer) Shutdown() {
	if b.smtpServer == nil {
		return
	}

	shutdownCtx, shutdownCtxCancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer shutdownCtxCancel()

	if err := b.smtpServer.Shutdown(shutdownCtx); err != nil {
		b.logger.Error("Failed to shutdown SMTP server", "error", err)
	}

	b.smtpServer = nil

}

func (b *BounceServer) Start(ctx context.Context, logger *slog.Logger) {
	logger.Info("Starting Bounce server!!!!!!!!!!!!!!!!!!!!!!!!!!!")
	b.logger = logger

	be := &BounceBackend{}

	smtpServer := smtp.NewServer(be)

	smtpServer.Addr = "0.0.0.0:1025"
	smtpServer.Domain = "localhost"
	smtpServer.WriteTimeout = 30 * time.Second
	smtpServer.ReadTimeout = 30 * time.Second
	smtpServer.MaxMessageBytes = 1024 * 1024
	smtpServer.MaxRecipients = 50
	smtpServer.AllowInsecureAuth = true

	b.smtpServer = smtpServer

	logger.Info("Starting Bounce server at", "addr", smtpServer.Addr)
	if err := smtpServer.ListenAndServe(); err != nil {
		logger.Error("Failed to start Bounce server", "error", err)
	}
}
