package main

import (
	"bufio"
	"crypto/tls"
	"fmt"
	"net"
	"strings"
	"sync/atomic"
	"testing"

	"github.com/stretchr/testify/assert"

	smtp "github.com/hyvor/relay/worker/smtp"
)

// tlsIntolerantServer is an SMTP server that offers STARTTLS but aborts the
// handshake when the client advertises TLS 1.3, which is how the servers in
// issue #466 behave. Set intolerant to false for a server that accepts any
// version.
type tlsIntolerantServer struct {
	listener    net.Listener
	cert        tls.Certificate
	intolerant  bool
	connections atomic.Int32
	tlsVersions chan uint16
}

func startTlsIntolerantSmtpServer(t *testing.T, intolerant bool) *tlsIntolerantServer {
	t.Helper()

	listener, err := net.Listen("tcp", "127.0.0.1:0")
	assert.NoError(t, err)
	t.Cleanup(func() { listener.Close() })

	server := &tlsIntolerantServer{
		listener:    listener,
		cert:        selfSignedCert(t),
		intolerant:  intolerant,
		tlsVersions: make(chan uint16, 8),
	}

	go func() {
		for {
			conn, err := listener.Accept()
			if err != nil {
				return
			}
			server.connections.Add(1)
			go server.handle(conn)
		}
	}()

	return server
}

func (s *tlsIntolerantServer) handle(conn net.Conn) {
	defer conn.Close()

	write := func(format string, args ...any) error {
		_, err := fmt.Fprintf(conn, format+"\r\n", args...)
		return err
	}

	reader := bufio.NewReader(conn)

	if write("220 test.local ESMTP") != nil {
		return
	}

	// plaintext phase: EHLO then STARTTLS
	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			return
		}

		command := strings.ToUpper(strings.TrimSpace(line))

		switch {
		case strings.HasPrefix(command, "EHLO"), strings.HasPrefix(command, "HELO"):
			if write("250-test.local Hello\r\n250 STARTTLS") != nil {
				return
			}
		case command == "STARTTLS":
			if write("220 Ready to start TLS") != nil {
				return
			}
			s.serveTls(conn)
			return
		case command == "QUIT":
			write("221 Bye")
			return
		default:
			if write("502 Command not implemented") != nil {
				return
			}
		}
	}
}

func (s *tlsIntolerantServer) serveTls(conn net.Conn) {

	config := &tls.Config{
		Certificates: []tls.Certificate{s.cert},
		GetConfigForClient: func(hello *tls.ClientHelloInfo) (*tls.Config, error) {
			// a real TLS 1.3-intolerant server aborts on the ClientHello
			// rather than negotiating down, which is exactly what makes the
			// failure unrecoverable on this connection
			if s.intolerant {
				for _, version := range hello.SupportedVersions {
					if version >= tls.VersionTLS13 {
						return nil, fmt.Errorf("tls 1.3 not supported")
					}
				}
			}
			return nil, nil
		},
	}

	tlsConn := tls.Server(conn, config)

	if err := tlsConn.Handshake(); err != nil {
		return
	}

	s.tlsVersions <- tlsConn.ConnectionState().Version

	// encrypted phase: accept the message
	reader := bufio.NewReader(tlsConn)
	write := func(format string, args ...any) error {
		_, err := fmt.Fprintf(tlsConn, format+"\r\n", args...)
		return err
	}

	inData := false

	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			return
		}

		trimmed := strings.TrimSpace(line)

		if inData {
			if trimmed == "." {
				inData = false
				if write("250 Message accepted") != nil {
					return
				}
			}
			continue
		}

		command := strings.ToUpper(trimmed)

		switch {
		case strings.HasPrefix(command, "EHLO"), strings.HasPrefix(command, "HELO"):
			err = write("250-test.local Hello\r\n250 SIZE 10240000")
		case strings.HasPrefix(command, "MAIL FROM"):
			err = write("250 Sender OK")
		case strings.HasPrefix(command, "RCPT TO"):
			err = write("250 Recipient OK")
		case command == "DATA":
			inData = true
			err = write("354 Send data")
		case command == "QUIT":
			write("221 Bye")
			return
		default:
			err = write("502 Command not implemented")
		}

		if err != nil {
			return
		}
	}
}

// pointSmtpClientAt makes createSmtpClient dial the test server, ignoring the
// host and local IP the worker would normally bind.
func pointSmtpClientAt(t *testing.T, address string) {
	t.Helper()

	original := createSmtpClient
	createSmtpClient = func(host string, _ string) (*smtp.Client, error) {
		conn, err := net.Dial("tcp", address)
		if err != nil {
			return nil, err
		}
		return smtp.NewClient(conn, host)
	}

	t.Cleanup(func() { createSmtpClient = original })
}

func testSendAndRecipient() (*SendRow, []*RecipientRow) {
	send := &SendRow{
		Id:        1,
		Uuid:      "test-uuid",
		From:      "test@hyvor.com",
		RawEmail:  "Subject: Test Email",
		QueueName: "default",
	}
	return send, []*RecipientRow{{Id: 1, Type: "to", Address: "accept@test.local"}}
}

func TestSendEmailToHost_FallsBackToTls12(t *testing.T) {

	server := startTlsIntolerantSmtpServer(t, true)
	pointSmtpClientAt(t, server.listener.Addr().String())

	// the cert is self-signed, so skip verification for the test; version
	// negotiation is what is under test here
	restoreVerify := skipTlsVerifyForTest(t)
	defer restoreVerify()

	send, recipients := testSendAndRecipient()

	conversation := sendEmailToHost(send, recipients, "test.local", "relay.com", "127.0.0.1", "smtp.relay.com")

	// the message got through on the second attempt
	assert.NoError(t, conversation.NetworkError)

	// without the fallback this is empty, so stop here rather than panicking
	if !assert.Len(t, conversation.RcptResults, 1) {
		t.FailNow()
	}
	assert.Equal(t, 250, conversation.RcptResults[0].Code)

	// exactly two connections: the rejected TLS 1.3 attempt and the fallback
	assert.Equal(t, int32(2), server.connections.Load())

	negotiated := <-server.tlsVersions
	assert.Equal(t, uint16(tls.VersionTLS12), negotiated)

	// the failed attempt is still visible in the conversation
	var starttlsSteps int
	for _, step := range conversation.Steps {
		if step.Name == SmtpStepStartTLS {
			starttlsSteps++
		}
	}
	assert.Equal(t, 2, starttlsSteps, "both STARTTLS attempts should be recorded")

}

func TestSendEmailToHost_NoFallbackWhenTlsSucceeds(t *testing.T) {

	server := startTlsIntolerantSmtpServer(t, false)
	pointSmtpClientAt(t, server.listener.Addr().String())

	restoreVerify := skipTlsVerifyForTest(t)
	defer restoreVerify()

	send, recipients := testSendAndRecipient()

	conversation := sendEmailToHost(send, recipients, "test.local", "relay.com", "127.0.0.1", "smtp.relay.com")

	assert.NoError(t, conversation.NetworkError)

	if !assert.Len(t, conversation.RcptResults, 1) {
		t.FailNow()
	}
	assert.Equal(t, 250, conversation.RcptResults[0].Code)

	// a healthy server must not pay for a second connection
	assert.Equal(t, int32(1), server.connections.Load())

	negotiated := <-server.tlsVersions
	assert.Equal(t, uint16(tls.VersionTLS13), negotiated)

}
