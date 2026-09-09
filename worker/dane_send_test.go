package main

import (
	"bufio"
	"context"
	"crypto/tls"
	"encoding/hex"
	"net"
	"testing"
	"time"

	smtp "github.com/hyvor/relay/worker/smtp"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestSendEmailToHost_DaneHandshakeProceedsToMail(t *testing.T) {
	certificate, key := testCertificate(t, false, nil, nil)
	clientConn, serverConn := net.Pipe()
	serverDone := make(chan error, 1)
	go func() {
		defer serverConn.Close()
		reader := bufio.NewReader(serverConn)
		writer := bufio.NewWriter(serverConn)
		write := func(response string) error {
			if _, err := writer.WriteString(response); err != nil {
				return err
			}
			return writer.Flush()
		}
		read := func() error {
			_, err := reader.ReadString('\n')
			return err
		}

		if err := write("220 mx.example.com ready\r\n"); err != nil {
			serverDone <- err
			return
		}
		if err := read(); err != nil {
			serverDone <- err
			return
		}
		if err := write("250-mx.example.com\r\n250-STARTTLS\r\n250 HELP\r\n"); err != nil {
			serverDone <- err
			return
		}
		if err := read(); err != nil {
			serverDone <- err
			return
		}
		if err := write("220 2.0.0 ready for TLS\r\n"); err != nil {
			serverDone <- err
			return
		}

		tlsServer := tls.Server(serverConn, &tls.Config{Certificates: []tls.Certificate{{
			Certificate: [][]byte{certificate.Raw},
			PrivateKey:  key,
		}}})
		if err := tlsServer.Handshake(); err != nil {
			serverDone <- err
			return
		}
		reader = bufio.NewReader(tlsServer)
		writer = bufio.NewWriter(tlsServer)
		if err := read(); err != nil {
			serverDone <- err
			return
		}
		if err := write("250-mx.example.com\r\n250 HELP\r\n"); err != nil {
			serverDone <- err
			return
		}
		if err := read(); err != nil {
			serverDone <- err
			return
		}
		if err := write("250 MAIL OK\r\n"); err != nil {
			serverDone <- err
			return
		}
		if err := read(); err != nil {
			serverDone <- err
			return
		}
		if err := write("250 RCPT OK\r\n"); err != nil {
			serverDone <- err
			return
		}
		if err := read(); err != nil {
			serverDone <- err
			return
		}
		if err := write("354 send data\r\n"); err != nil {
			serverDone <- err
			return
		}
		for {
			line, err := reader.ReadString('\n')
			if err != nil {
				serverDone <- err
				return
			}
			if line == ".\r\n" {
				break
			}
		}
		serverDone <- write("250 queued\r\n")
	}()

	originalTLSA := lookupTLSAFunc
	originalCreateClient := createSmtpClient
	cache := getProcessSharedCache()
	lookupTLSAFunc = func(context.Context, *SharedCache, string) (TLSAResult, error) {
		return TLSAResult{State: TLSAStateSecureRecords, Records: []TLSARecord{{
			CertificateUsage:       3,
			Selector:               0,
			MatchingType:           0,
			CertificateAssociation: hex.EncodeToString(certificate.Raw),
		}}}, nil
	}
	require.NoError(t, cache.Set(context.Background(), dnsCacheKey("mx", "example.com"), MxCacheValue{
		Records: []MxRecord{{Host: "mx.example.com"}},
		Secure:  true,
	}, time.Minute))
	createSmtpClient = func(string, string) (*smtp.Client, error) {
		return smtp.NewClient(clientConn, "mx.example.com")
	}
	t.Cleanup(func() {
		lookupTLSAFunc = originalTLSA
		createSmtpClient = originalCreateClient
		_ = cache.Delete(context.Background(), dnsCacheKey("mx", "example.com"))
	})

	conversation := sendEmailToHostHandler(
		&SendRow{From: "sender@example.com", RawEmail: "Subject: test\r\n\r\nbody"},
		[]*RecipientRow{{Id: 1, Address: "recipient@example.com"}},
		"mx.example.com", "relay.example.com", "127.0.0.1", "relay.example.com", true,
	)

	assert.NoError(t, conversation.NetworkError)
	require.Len(t, conversation.RcptResults, 1)
	assert.Equal(t, 250, conversation.RcptResults[0].Code)
	select {
	case err := <-serverDone:
		assert.NoError(t, err)
	case <-time.After(5 * time.Second):
		t.Fatal("SMTP test server did not finish")
	}
}
