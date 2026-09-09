package main

import (
	"crypto/sha256"
	"crypto/sha512"
	"crypto/x509"
	"encoding/hex"
	"errors"
	"fmt"
	"strings"
)

var ErrDANEAuthentication = errors.New("DANE certificate authentication failed")

// verifyDANECertificates evaluates TLSA records against the peer chain. The
// peer slice contains the leaf followed by any certificates sent by the peer.
// DANE-EE can authenticate without a public CA; DANE-TA and PKIX usages still
// require ordinary chain verification.
func verifyDANECertificates(peer []*x509.Certificate, records []TLSARecord, serverName string) error {
	if len(peer) == 0 {
		return fmt.Errorf("%w: peer sent no certificates", ErrDANEAuthentication)
	}
	if len(records) == 0 {
		return fmt.Errorf("%w: no usable TLSA records", ErrDANEAuthentication)
	}

	for _, record := range records {
		if record.CertificateUsage == 3 && tlsaMatchesCertificate(record, peer[0]) {
			return nil
		}
	}

	for _, record := range records {
		if record.CertificateUsage == 2 && matchesTrustAnchor(record, peer, serverName) {
			return nil
		}
	}

	for _, record := range records {
		switch record.CertificateUsage {
		case 1:
			if tlsaMatchesCertificate(record, peer[0]) && verifyPKIXChain(peer, serverName, nil) == nil {
				return nil
			}
		case 0:
			if matchesTrustAnchor(record, peer, serverName) {
				return nil
			}
		}
	}

	return fmt.Errorf("%w: no TLSA association matched the peer certificate", ErrDANEAuthentication)
}

func tlsaMatchesCertificate(record TLSARecord, certificate *x509.Certificate) bool {
	if certificate == nil {
		return false
	}
	selected := certificate.Raw
	if record.Selector == 1 {
		selected = certificate.RawSubjectPublicKeyInfo
	}

	association, err := hex.DecodeString(strings.TrimSpace(record.CertificateAssociation))
	if err != nil {
		return false
	}

	switch record.MatchingType {
	case 0:
		return string(selected) == string(association)
	case 1:
		hash := sha256.Sum256(selected)
		return string(hash[:]) == string(association)
	case 2:
		hash := sha512.Sum512(selected)
		return string(hash[:]) == string(association)
	default:
		return false
	}
}

func matchesTrustAnchor(record TLSARecord, peer []*x509.Certificate, serverName string) bool {
	for index := 1; index < len(peer); index++ {
		candidate := peer[index]
		if !tlsaMatchesCertificate(record, candidate) {
			continue
		}
		if verifyPKIXChain(peer, serverName, candidate) == nil {
			return true
		}
	}
	return false
}

func verifyPKIXChain(peer []*x509.Certificate, serverName string, trustAnchor *x509.Certificate) error {
	if len(peer) == 0 {
		return fmt.Errorf("%w: empty peer chain", ErrDANEAuthentication)
	}

	intermediates := x509.NewCertPool()
	for _, certificate := range peer[1:] {
		intermediates.AddCert(certificate)
	}

	roots := (*x509.CertPool)(nil)
	if trustAnchor != nil {
		roots = x509.NewCertPool()
		roots.AddCert(trustAnchor)
	}

	_, err := peer[0].Verify(x509.VerifyOptions{
		DNSName:       serverName,
		Roots:         roots,
		Intermediates: intermediates,
		KeyUsages:     []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth},
	})
	return err
}
