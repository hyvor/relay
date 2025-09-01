package main

import (
	"context"
	"net/smtp"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestIncomingServer(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	server := &IncomingMailServer{
		logger:  slogDiscard(),
		metrics: newMetrics(),
	}

	smtpServerPort = ":25251"
	go server.Start(ctx, "example.com", 2)

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

}
