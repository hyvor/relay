package main

import (
	"crypto/tls"
	"errors"
	"strings"
)

// tlsFallbackMaxVersion is the ceiling used when retrying a host whose TLS
// handshake failed. Some receiving servers, particularly older or
// middlebox-fronted ones, abort the handshake when they see a TLS 1.3
// ClientHello even though they would happily negotiate TLS 1.2.
const tlsFallbackMaxVersion = tls.VersionTLS12

// isTlsVersionNegotiationError reports whether err looks like a handshake
// failure that capping the TLS version might get past.
//
// The error shapes here were confirmed against crypto/tls rather than
// guessed, because they are not uniform:
//
//   - A peer that rejects our ClientHello answers with an alert, which
//     crypto/tls surfaces as the unexported *tls.permanentError wrapping a
//     string such as "remote error: tls: protocol version not supported".
//     There is no exported type to match on, so this case has to be detected
//     by message.
//   - A peer that answers with something that is not a TLS record at all
//     gives us a tls.RecordHeaderError.
//   - A certificate problem gives us *tls.CertificateVerificationError. Its
//     message also contains "tls: ", so it must be excluded before the
//     message check, and it is excluded because lowering the version cannot
//     fix an untrusted certificate.
func isTlsVersionNegotiationError(err error) bool {
	if err == nil {
		return false
	}

	var certErr *tls.CertificateVerificationError
	if errors.As(err, &certErr) {
		return false
	}

	var recordErr tls.RecordHeaderError
	if errors.As(err, &recordErr) {
		return true
	}

	// covers both the remote alerts above and local failures such as
	// "tls: server selected unsupported protocol version"
	return strings.Contains(err.Error(), "tls: ")
}
