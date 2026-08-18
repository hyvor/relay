package main

import (
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"errors"
	"math/big"
	"net"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

// selfSignedCert builds a throwaway certificate for the test TLS servers.
func selfSignedCert(t *testing.T) tls.Certificate {
	t.Helper()

	key, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	assert.NoError(t, err)

	template := &x509.Certificate{
		SerialNumber: big.NewInt(1),
		Subject:      pkix.Name{CommonName: "localhost"},
		NotBefore:    time.Now().Add(-time.Hour),
		NotAfter:     time.Now().Add(time.Hour),
		DNSNames:     []string{"localhost"},
	}

	der, err := x509.CreateCertificate(rand.Reader, template, template, &key.PublicKey, key)
	assert.NoError(t, err)

	return tls.Certificate{Certificate: [][]byte{der}, PrivateKey: key}
}

// startTlsServer starts a TLS listener pinned to the given version range.
func startTlsServer(t *testing.T, minVersion, maxVersion uint16) net.Listener {
	t.Helper()

	listener, err := net.Listen("tcp", "127.0.0.1:0")
	assert.NoError(t, err)
	t.Cleanup(func() { listener.Close() })

	cert := selfSignedCert(t)

	go func() {
		for {
			conn, err := listener.Accept()
			if err != nil {
				return
			}
			go func() {
				tlsConn := tls.Server(conn, &tls.Config{
					Certificates: []tls.Certificate{cert},
					MinVersion:   minVersion,
					MaxVersion:   maxVersion,
				})
				tlsConn.Handshake()
				tlsConn.Close()
			}()
		}
	}()

	return listener
}

// handshakeAgainst dials addr and drives the lazy handshake to completion the
// same way the worker does, by writing and then reading.
func handshakeAgainst(t *testing.T, addr string, config *tls.Config) error {
	t.Helper()

	conn, err := net.Dial("tcp", addr)
	assert.NoError(t, err)
	defer conn.Close()

	tlsConn := tls.Client(conn, config)

	if _, err := tlsConn.Write([]byte("EHLO test\r\n")); err != nil {
		return err
	}

	buf := make([]byte, 1)
	_, err = tlsConn.Read(buf)

	return err
}

func TestIsTlsVersionNegotiationErrorOnRemoteAlert(t *testing.T) {

	// server speaks only TLS 1.3, client is capped at 1.2, so the server
	// rejects the ClientHello. This is the shape of the failure reported in
	// the issue, and crypto/tls gives it no exported type.
	server := startTlsServer(t, tls.VersionTLS13, tls.VersionTLS13)

	err := handshakeAgainst(t, server.Addr().String(), &tls.Config{
		ServerName:         "localhost",
		InsecureSkipVerify: true,
		MaxVersion:         tls.VersionTLS12,
	})

	assert.Error(t, err)
	assert.True(t, isTlsVersionNegotiationError(err), "got %q", err)

}

func TestIsTlsVersionNegotiationErrorOnNonTlsPeer(t *testing.T) {

	// a peer that answers with plaintext gives a tls.RecordHeaderError
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	assert.NoError(t, err)
	t.Cleanup(func() { listener.Close() })

	go func() {
		for {
			conn, err := listener.Accept()
			if err != nil {
				return
			}
			go func() {
				conn.Write([]byte("500 not tls\r\n"))
				conn.Close()
			}()
		}
	}()

	handshakeErr := handshakeAgainst(t, listener.Addr().String(), &tls.Config{
		ServerName:         "localhost",
		InsecureSkipVerify: true,
	})

	var recordErr tls.RecordHeaderError
	assert.True(t, errors.As(handshakeErr, &recordErr))
	assert.True(t, isTlsVersionNegotiationError(handshakeErr), "got %q", handshakeErr)

}

func TestIsTlsVersionNegotiationErrorIgnoresCertificateFailures(t *testing.T) {

	// a certificate that does not verify is not something a lower TLS
	// version can fix, so it must not trigger the fallback. Note its message
	// also contains "tls: ", which is why the type check comes first.
	server := startTlsServer(t, tls.VersionTLS12, tls.VersionTLS13)

	err := handshakeAgainst(t, server.Addr().String(), &tls.Config{
		ServerName: "localhost",
	})

	var certErr *tls.CertificateVerificationError
	assert.True(t, errors.As(err, &certErr))
	assert.Contains(t, err.Error(), "tls: ")
	assert.False(t, isTlsVersionNegotiationError(err), "got %q", err)

}

func TestIsTlsVersionNegotiationErrorIgnoresUnrelatedErrors(t *testing.T) {

	assert.False(t, isTlsVersionNegotiationError(nil))
	assert.False(t, isTlsVersionNegotiationError(errors.New("connection reset by peer")))
	assert.False(t, isTlsVersionNegotiationError(errors.New("EOF")))
	assert.False(t, isTlsVersionNegotiationError(errors.New("i/o timeout")))

}

func TestTlsFallbackReachesAVersionIntolerantPeer(t *testing.T) {

	// the fallback has to actually work: a server that will not speak above
	// TLS 1.2 succeeds once the client caps itself there
	server := startTlsServer(t, tls.VersionTLS12, tls.VersionTLS12)

	err := handshakeAgainst(t, server.Addr().String(), &tls.Config{
		ServerName:         "localhost",
		InsecureSkipVerify: true,
		MaxVersion:         tlsFallbackMaxVersion,
	})

	// the server closes right after the handshake, so EOF means the
	// handshake itself succeeded
	assert.False(t, isTlsVersionNegotiationError(err), "got %v", err)

}

// skipTlsVerifyForTest relaxes certificate verification for the outgoing
// STARTTLS config, so tests can use throwaway self-signed certificates while
// still exercising real version negotiation.
func skipTlsVerifyForTest(t *testing.T) func() {
	t.Helper()

	original := newSendTlsConfig
	newSendTlsConfig = func(host string, maxVersion uint16) *tls.Config {
		config := original(host, maxVersion)
		config.InsecureSkipVerify = true
		return config
	}

	return func() { newSendTlsConfig = original }
}
