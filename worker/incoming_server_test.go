package main

import (
	"context"
	"net"
	"net/smtp"
	"strings"
	"testing"
	"time"

	"github.com/hyvor/relay/worker/smtp_interface"
	"github.com/stretchr/testify/assert"
)

type fakeAddr struct {
	network string
	addr    string
}

func (f fakeAddr) Network() string { return f.network }
func (f fakeAddr) String() string  { return f.addr }

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

	go server.Set("example.com", 2, GoStateMailTls{Enabled: false}, GoStateSecurity{})

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
			ApiKey:   "test-api-key",
			ClientIp: "203.0.113.5",
		},
		metrics: newMetrics(),
	}

	var calledApiKey string
	var calledApiRequest *smtp_interface.ApiRequest
	var calledClientIp string

	CallConsoleSendApi = func(
		ctx context.Context,
		apiKey string,
		body *smtp_interface.ApiRequest,
		clientIp string,
	) error {
		calledApiKey = apiKey
		calledApiRequest = body
		calledClientIp = clientIp
		return nil
	}

	reader := strings.NewReader("Subject: Test email\r\nFrom: sender@example.com\r\n\r\nThis is a test email.")

	err := session.Data(reader)
	assert.NoError(t, err)

	assert.Equal(t, "test-api-key", calledApiKey)
	assert.NotNil(t, calledApiRequest)
	assert.Equal(t, "Test email", calledApiRequest.Subject)
	assert.Equal(t, "This is a test email.", calledApiRequest.BodyText)
	assert.Equal(t, "203.0.113.5", calledClientIp)

}

func TestClientIpFromAddr(t *testing.T) {

	assert.Equal(t, "", clientIpFromAddr(nil))

	tcp := &net.TCPAddr{IP: net.ParseIP("203.0.113.5"), Port: 1234}
	assert.Equal(t, "203.0.113.5", clientIpFromAddr(tcp))

}

func TestIncomingServer_UnauthenticatedSending(t *testing.T) {

	t.Run("forwards to API with system API key when AllowUnauthenticatedSending is true", func(t *testing.T) {
		session := &Session{
			logger:  slogDiscard(),
			metrics: newMetrics(),
			security: GoStateSecurity{
				AllowUnauthenticatedSending: true,
			},
			systemApiKey: "test-system-api-key",
			incomingMail: IncomingMail{
				RcptTo:         "recipient@other-domain.com",
				MailFrom:       "sender@example.com",
				InstanceDomain: "example.com",
			},
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

		assert.Equal(t, "test-system-api-key", calledApiKey)
		assert.NotNil(t, calledApiRequest)
		assert.Equal(t, "Test email", calledApiRequest.Subject)
	})

	t.Run("does not forward instance-domain emails (bounce/FBL path preserved)", func(t *testing.T) {
		session := &Session{
			logger:  slogDiscard(),
			metrics: newMetrics(),
			security: GoStateSecurity{
				AllowUnauthenticatedSending: true,
			},
			systemApiKey: "test-system-api-key",
			mailChannel:  make(chan *IncomingMail, 1),
			incomingMail: IncomingMail{
				RcptTo:         "bounce+abc123@example.com",
				MailFrom:       "sender@external.com",
				InstanceDomain: "example.com",
			},
		}

		var called bool
		CallConsoleSendApi = func(
			ctx context.Context,
			apiKey string,
			body *smtp_interface.ApiRequest,
		) error {
			called = true
			return nil
		}

		reader := strings.NewReader("Subject: Bounce\r\n\r\nBounce body.")

		err := session.Data(reader)
		assert.NoError(t, err)

		assert.False(t, called, "should not call send API for instance-domain emails")
		select {
		case mail := <-session.mailChannel:
			assert.NotNil(t, mail)
		default:
			t.Error("expected mail to be sent to mailChannel")
		}
	})

	t.Run("does not forward when systemApiKey is empty", func(t *testing.T) {
		session := &Session{
			logger:  slogDiscard(),
			metrics: newMetrics(),
			security: GoStateSecurity{
				AllowUnauthenticatedSending: true,
			},
			systemApiKey: "",
			mailChannel:  make(chan *IncomingMail, 1),
			incomingMail: IncomingMail{
				RcptTo:         "recipient@other-domain.com",
				MailFrom:       "sender@example.com",
				InstanceDomain: "example.com",
			},
		}

		var called bool
		CallConsoleSendApi = func(
			ctx context.Context,
			apiKey string,
			body *smtp_interface.ApiRequest,
		) error {
			called = true
			return nil
		}

		reader := strings.NewReader("Subject: Test\r\n\r\nBody.")

		err := session.Data(reader)
		assert.NoError(t, err)

		assert.False(t, called, "should not call send API when systemApiKey is empty")
	})

	t.Run("rejects non-instance-domain RCPT when AllowUnauthenticatedSending is false", func(t *testing.T) {
		session := &Session{
			logger:  slogDiscard(),
			metrics: newMetrics(),
			security: GoStateSecurity{
				AllowUnauthenticatedSending: false,
			},
			incomingMail: IncomingMail{
				InstanceDomain: "example.com",
			},
		}

		err := session.Rcpt("recipient@other-domain.com", nil)
		assert.Error(t, err)
		assert.Contains(t, err.Error(), "only accepts emails for example.com")
	})

	t.Run("accepts non-instance-domain RCPT when AllowUnauthenticatedSending is true", func(t *testing.T) {
		session := &Session{
			logger:  slogDiscard(),
			metrics: newMetrics(),
			security: GoStateSecurity{
				AllowUnauthenticatedSending: true,
			},
			incomingMail: IncomingMail{
				InstanceDomain: "example.com",
			},
		}

		err := session.Rcpt("recipient@other-domain.com", nil)
		assert.NoError(t, err)
		assert.Equal(t, "recipient@other-domain.com", session.incomingMail.RcptTo)
	})

}
