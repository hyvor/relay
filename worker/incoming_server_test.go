package main

import (
	"context"
	"net/smtp"
	"strings"
	"testing"
	"time"

	"github.com/hyvor/relay/worker/smtp_interface"
	"github.com/stretchr/testify/assert"
)

func TestIncomingServer(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	server := &IncomingMailServer{
		ctx:     ctx,
		logger:  slogDiscard(),
		metrics: newMetrics(),
	}

	originalSmtpServerPort1 := smtpServerPort1
	originalSmtpServerPort2 := smtpServerPort2

	smtpServerPort1 = ":25251"
	smtpServerPort2 = ":25252"
	defer func() {
		smtpServerPort1 = originalSmtpServerPort1
		smtpServerPort2 = originalSmtpServerPort2
	}()

	go server.Set("example.com", 2, GoStateMailTls{Enabled: false})

	time.Sleep(100 * time.Millisecond)

	// send SMTP message to localhost:25251
	conn, err := smtp.Dial("localhost:25251")
	assert.NoError(t, err)

	err = conn.Mail("sender@example.com")
	assert.NoError(t, err)

	err = conn.Rcpt("recipient@example.com")
	assert.NoError(t, err)

	w, err := conn.Data()
	assert.NoError(t, err)

	_, err = w.Write([]byte("Subject: Test email\r\n\r\nThis is a test email."))
	assert.NoError(t, err)

	err = w.Close()
	assert.NoError(t, err)

	err = conn.Quit()
	assert.NoError(t, err)

	conn.Close()

	// RCPT validation
	conn, err = smtp.Dial("localhost:25251")
	assert.NoError(t, err)

	err = conn.Mail("sender@example.com")
	assert.NoError(t, err)

	err = conn.Rcpt("recipient@example.org")
	assert.Equal(t, err.Error(), "451 4.0.0 this SMTP server only accepts emails for example.com")

	conn.Close()

	// AUTH
	conn, err = smtp.Dial("localhost:25252")
	assert.NoError(t, err)

	err = conn.Auth(smtp.PlainAuth("", "user", "password", "localhost"))
	assert.NoError(t, err)

}

func TestIncomingServer_HandlesApiKeyCallsSynchronously(t *testing.T) {

	session := &Session{
		logger: slogDiscard(),
		incomingMail: IncomingMail{
			ApiKey: "test-api-key",
		},
		metrics: newMetrics(),
	}

	var calledApiKey string
	var calledApiRequest *smtp_interface.ApiRequest

	CallConsoleSendApi = func(
		ctx context.Context,
		apiKey string,
		body *smtp_interface.ApiRequest,
	) error {
		calledApiKey = apiKey
		calledApiRequest = body
		return nil
	}

	reader := strings.NewReader("Subject: Test email\r\nFrom: sender@example.com\r\n\r\nThis is a test email.")

	err := session.Data(reader)
	assert.NoError(t, err)

	assert.Equal(t, "test-api-key", calledApiKey)
	assert.NotNil(t, calledApiRequest)
	assert.Equal(t, "Test email", calledApiRequest.Subject)
	assert.Equal(t, "This is a test email.", calledApiRequest.BodyText)

}