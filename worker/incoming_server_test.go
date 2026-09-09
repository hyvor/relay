package main

import (
	"bytes"
	"context"
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"encoding/pem"
	"math/big"
	"net"
	"net/smtp"
	"strings"
	"testing"
	"time"

	"github.com/hyvor/relay/worker/smtp_interface"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

type fakeAddr struct {
	network string
	addr    string
}

func (f fakeAddr) Network() string { return f.network }
func (f fakeAddr) String() string  { return f.addr }

func testMailTLS(t *testing.T) (GoStateMailTls, *x509.CertPool) {
	t.Helper()

	privateKey, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	require.NoError(t, err)

	template := &x509.Certificate{
		SerialNumber: big.NewInt(1),
		DNSNames:     []string{"localhost"},
		NotBefore:    time.Now().Add(-time.Hour),
		NotAfter:     time.Now().Add(time.Hour),
		KeyUsage:     x509.KeyUsageDigitalSignature,
		ExtKeyUsage:  []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth},
	}
	certificate, err := x509.CreateCertificate(rand.Reader, template, template, &privateKey.PublicKey, privateKey)
	require.NoError(t, err)

	privateKeyDer, err := x509.MarshalECPrivateKey(privateKey)
	require.NoError(t, err)

	certificatePem := pem.EncodeToMemory(&pem.Block{Type: "CERTIFICATE", Bytes: certificate})
	privateKeyPem := pem.EncodeToMemory(&pem.Block{Type: "EC PRIVATE KEY", Bytes: privateKeyDer})
	rootCAs := x509.NewCertPool()
	require.True(t, rootCAs.AppendCertsFromPEM(certificatePem))

	return GoStateMailTls{
		Enabled:     true,
		Certificate: string(certificatePem),
		PrivateKey:  string(privateKeyPem),
	}, rootCAs
}

func TestIncomingServer(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	server := &IncomingMailServer{
		ctx:     ctx,
		logger:  slogDiscard(),
		metrics: newMetrics(),
	}
	defer server.Shutdown()

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

	err = conn.Rcpt("another@example.com")
	assert.EqualError(t, err, "452 4.5.3 Maximum limit of 1 recipients reached")

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
	assert.EqualError(t, err, "523 5.7.10 TLS is required")

	conn.Close()
}

func TestIncomingServer_DoesNotLogPrivateKeyOnTlsFailure(t *testing.T) {
	var logs bytes.Buffer
	privateKey := "private-key-must-not-be-logged"
	server := &IncomingMailServer{
		ctx:     context.Background(),
		logger:  slogBuffer(&logs),
		metrics: newMetrics(),
	}

	server.StartSmtpServer(
		context.Background(),
		":0",
		"example.com",
		GoStateMailTls{Enabled: true, Certificate: "invalid", PrivateKey: privateKey},
		make(chan *IncomingMail),
		1,
	)

	assert.Contains(t, logs.String(), "Failed to load TLS certificate for incoming mail server")
	assert.NotContains(t, logs.String(), privateKey)
}

func TestSession_DataQueuesIndependentSnapshots(t *testing.T) {
	mailChannel := make(chan *IncomingMail, 2)
	session := &Session{
		ctx:         context.Background(),
		logger:      slogDiscard(),
		metrics:     newMetrics(),
		mailChannel: mailChannel,
		incomingMail: IncomingMail{
			InstanceDomain: "example.com",
			ClientIp:       "203.0.113.5",
		},
	}

	require.NoError(t, session.Mail("sender-one@example.org", nil))
	require.NoError(t, session.Rcpt("recipient-one@example.com", nil))
	require.NoError(t, session.Data(strings.NewReader("first body")))

	session.Reset()
	require.NoError(t, session.Mail("sender-two@example.org", nil))
	require.NoError(t, session.Rcpt("recipient-two@example.com", nil))
	require.NoError(t, session.Data(strings.NewReader("second body")))

	first := <-mailChannel
	second := <-mailChannel
	assert.NotSame(t, first, second)
	assert.NotSame(t, &session.incomingMail, first)
	assert.Equal(t, "sender-one@example.org", first.MailFrom)
	assert.Equal(t, "recipient-one@example.com", first.RcptTo)
	assert.Equal(t, []byte("first body"), first.Data)
	assert.Equal(t, "sender-two@example.org", second.MailFrom)
	assert.Equal(t, "recipient-two@example.com", second.RcptTo)
	assert.Equal(t, []byte("second body"), second.Data)
}

func TestSession_DataReturnsWhenWorkerContextIsCanceled(t *testing.T) {
	workerCtx, cancelWorkers := context.WithCancel(context.Background())
	session := &Session{
		ctx:         workerCtx,
		logger:      slogDiscard(),
		metrics:     newMetrics(),
		mailChannel: make(chan *IncomingMail),
	}
	cancelWorkers()

	result := make(chan error, 1)
	go func() {
		result <- session.Data(strings.NewReader("body"))
	}()

	select {
	case err := <-result:
		assert.ErrorIs(t, err, context.Canceled)
	case <-time.After(time.Second):
		t.Fatal("DATA remained blocked after worker shutdown")
	}
}

func TestSession_RcptRejectsAdditionalRecipient(t *testing.T) {
	session := &Session{
		incomingMail: IncomingMail{InstanceDomain: "example.com"},
	}

	require.NoError(t, session.Rcpt("first@example.com", nil))
	err := session.Rcpt("second@example.com", nil)

	assert.EqualError(t, err, "SMTP error 452: only one recipient is supported")
	assert.Equal(t, "first@example.com", session.incomingMail.RcptTo)
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

	originalCallConsoleSendApi := CallConsoleSendApi
	t.Cleanup(func() { CallConsoleSendApi = originalCallConsoleSendApi })
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

	assert.Equal(t, "203.0.113.7", clientIpFromAddr(fakeAddr{network: "tcp", addr: "203.0.113.7:25"}))

	assert.Equal(t, "/tmp/socket", clientIpFromAddr(fakeAddr{network: "unix", addr: "/tmp/socket"}))
}

func TestIncomingServer_CapturesClientIp(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	server := &IncomingMailServer{
		ctx:     ctx,
		logger:  slogDiscard(),
		metrics: newMetrics(),
	}
	defer server.Shutdown()

	originalSmtpServerPort1 := smtpServerPort1
	originalSmtpServerPort2 := smtpServerPort2

	smtpServerPort1 = ":25351"
	smtpServerPort2 = ":25352"
	defer func() {
		smtpServerPort1 = originalSmtpServerPort1
		smtpServerPort2 = originalSmtpServerPort2
	}()

	var capturedClientIp string
	originalCallConsoleSendApi := CallConsoleSendApi
	t.Cleanup(func() { CallConsoleSendApi = originalCallConsoleSendApi })
	CallConsoleSendApi = func(
		ctx context.Context,
		apiKey string,
		body *smtp_interface.ApiRequest,
		clientIp string,
	) error {
		capturedClientIp = clientIp
		return nil
	}

	mailTLS, rootCAs := testMailTLS(t)
	go server.Set("example.com", 2, mailTLS)
	time.Sleep(100 * time.Millisecond)

	conn, err := smtp.Dial("localhost:25352")
	assert.NoError(t, err)
	err = conn.StartTLS(&tls.Config{
		MinVersion: tls.VersionTLS12,
		RootCAs:    rootCAs,
		ServerName: "localhost",
	})
	assert.NoError(t, err)

	err = conn.Auth(smtp.PlainAuth("", "user", "test-api-key", "localhost"))
	assert.NoError(t, err)

	err = conn.Mail("sender@example.com")
	assert.NoError(t, err)

	err = conn.Rcpt("recipient@example.org")
	assert.NoError(t, err)

	w, err := conn.Data()
	assert.NoError(t, err)

	_, err = w.Write([]byte("Subject: Test email\r\nFrom: sender@example.com\r\n\r\nThis is a test email."))
	assert.NoError(t, err)

	err = w.Close()
	assert.NoError(t, err)

	conn.Quit()
	conn.Close()

	// loopback connection: client IP should be 127.0.0.1 or ::1
	assert.True(t, capturedClientIp == "127.0.0.1" || capturedClientIp == "::1",
		"expected loopback IP, got %q", capturedClientIp)
}
