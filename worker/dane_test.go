package main

import (
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/sha512"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/hex"
	"math/big"
	"strings"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestDANEVerifyDaneEESPKISHA256(t *testing.T) {
	certificate, _ := testCertificate(t, false, nil, nil)
	hash := sha256.Sum256(certificate.RawSubjectPublicKeyInfo)
	record := TLSARecord{
		CertificateUsage:       3,
		Selector:               1,
		MatchingType:           1,
		CertificateAssociation: hex.EncodeToString(hash[:]),
	}

	assert.NoError(t, verifyDANECertificates([]*x509.Certificate{certificate}, []TLSARecord{record}, "mail.example.com"))
	assert.ErrorIs(t, verifyDANECertificates([]*x509.Certificate{certificate}, []TLSARecord{{
		CertificateUsage:       3,
		Selector:               0,
		MatchingType:           1,
		CertificateAssociation: strings.Repeat("00", 32),
	}}, "mail.example.com"), ErrDANEAuthentication)
}

func TestDANEVerifyDaneEEFullCertificate(t *testing.T) {
	certificate, _ := testCertificate(t, false, nil, nil)
	record := TLSARecord{
		CertificateUsage:       3,
		Selector:               0,
		MatchingType:           0,
		CertificateAssociation: hex.EncodeToString(certificate.Raw),
	}

	assert.NoError(t, verifyDANECertificates([]*x509.Certificate{certificate}, []TLSARecord{record}, "wrong.example.com"))
}

func TestDANEVerifyDaneTrustAnchor(t *testing.T) {
	root, rootKey := testCertificate(t, true, nil, nil)
	leaf, _ := testCertificate(t, false, root, rootKey)
	hash := sha256.Sum256(root.Raw)
	record := TLSARecord{
		CertificateUsage:       2,
		Selector:               0,
		MatchingType:           1,
		CertificateAssociation: hex.EncodeToString(hash[:]),
	}

	assert.NoError(t, verifyDANECertificates([]*x509.Certificate{leaf, root}, []TLSARecord{record}, "mail.example.com"))
}

func TestDANEVerifyFullTrustAnchorNotSentByPeer(t *testing.T) {
	root, rootKey := testCertificate(t, true, nil, nil)
	leaf, _ := testCertificate(t, false, root, rootKey)
	record := TLSARecord{
		CertificateUsage:       2,
		Selector:               0,
		MatchingType:           0,
		CertificateAssociation: hex.EncodeToString(root.Raw),
	}

	assert.NoError(t, verifyDANECertificates([]*x509.Certificate{leaf}, []TLSARecord{record}, "mail.example.com"))
}

func TestDANEVerifyTrustAnchorRejectsMissingReferenceIdentifier(t *testing.T) {
	root, rootKey := testCertificate(t, true, nil, nil)
	leaf, _ := testCertificate(t, false, root, rootKey)
	record := TLSARecord{CertificateUsage: 2, Selector: 0, MatchingType: 1, CertificateAssociation: hex.EncodeToString(sha256Bytes(root.Raw))}

	assert.ErrorIs(t, verifyDANECertificates([]*x509.Certificate{leaf, root}, []TLSARecord{record}), ErrDANEAuthentication)
	assert.ErrorIs(t, verifyDANECertificates([]*x509.Certificate{leaf, root}, []TLSARecord{record}, ""), ErrDANEAuthentication)
}

func TestDANEVerifyTrustAnchorSupportsCommonNameOnly(t *testing.T) {
	root, rootKey := testCertificate(t, true, nil, nil)
	leaf, _ := testCertificateWithDNSNames(t, false, root, rootKey)
	record := TLSARecord{CertificateUsage: 2, Selector: 0, MatchingType: 1, CertificateAssociation: hex.EncodeToString(sha256Bytes(root.Raw))}

	assert.NoError(t, verifyDANECertificates([]*x509.Certificate{leaf, root}, []TLSARecord{record}, "mail.example.com"))
}

func TestDANEVerifyTrustAnchorRejectsWrongReferenceIdentifier(t *testing.T) {
	root, rootKey := testCertificate(t, true, nil, nil)
	leaf, _ := testCertificateWithDNSNames(t, false, root, rootKey, "other.example.com")
	record := TLSARecord{CertificateUsage: 2, Selector: 0, MatchingType: 1, CertificateAssociation: hex.EncodeToString(sha256Bytes(root.Raw))}

	assert.ErrorIs(t, verifyDANECertificates([]*x509.Certificate{leaf, root}, []TLSARecord{record}, "mail.example.com"), ErrDANEAuthentication)
}

func TestDANEVerifyTrustAnchorPrefersDNSNameOverCommonName(t *testing.T) {
	root, rootKey := testCertificate(t, true, nil, nil)
	leaf, _ := testCertificateWithDNSNames(t, false, root, rootKey, "other.example.com")
	record := TLSARecord{CertificateUsage: 2, Selector: 0, MatchingType: 1, CertificateAssociation: hex.EncodeToString(sha256Bytes(root.Raw))}

	assert.ErrorIs(t, verifyDANECertificates([]*x509.Certificate{leaf, root}, []TLSARecord{record}, "mail.example.com"), ErrDANEAuthentication)
}

func TestDANEVerifyPrefersStrongerDigest(t *testing.T) {
	certificate, _ := testCertificate(t, false, nil, nil)
	sha512Hash := sha512Bytes(certificate.Raw)
	records := []TLSARecord{
		{CertificateUsage: 3, Selector: 0, MatchingType: 1, CertificateAssociation: hex.EncodeToString(make([]byte, 32))},
		{CertificateUsage: 3, Selector: 0, MatchingType: 2, CertificateAssociation: hex.EncodeToString(sha512Hash)},
	}

	assert.NoError(t, verifyDANECertificates([]*x509.Certificate{certificate}, records, "mail.example.com"))
}

func testCertificate(t *testing.T, isCA bool, issuer *x509.Certificate, issuerKey *rsa.PrivateKey) (*x509.Certificate, *rsa.PrivateKey) {
	return testCertificateWithDNSNames(t, isCA, issuer, issuerKey, "mail.example.com")
}

func testCertificateWithDNSNames(t *testing.T, isCA bool, issuer *x509.Certificate, issuerKey *rsa.PrivateKey, dnsNames ...string) (*x509.Certificate, *rsa.PrivateKey) {
	t.Helper()
	key, err := rsa.GenerateKey(rand.Reader, 2048)
	require.NoError(t, err)
	serial, err := rand.Int(rand.Reader, new(big.Int).Lsh(big.NewInt(1), 120))
	require.NoError(t, err)
	template := &x509.Certificate{
		SerialNumber:          serial,
		Subject:               pkix.Name{CommonName: "mail.example.com"},
		DNSNames:              dnsNames,
		NotBefore:             time.Now().Add(-time.Minute),
		NotAfter:              time.Now().Add(time.Hour),
		BasicConstraintsValid: true,
		IsCA:                  isCA,
		KeyUsage:              x509.KeyUsageDigitalSignature,
	}
	if isCA {
		template.KeyUsage |= x509.KeyUsageCertSign
	}
	if issuer == nil {
		issuer = template
		issuerKey = key
	}
	der, err := x509.CreateCertificate(rand.Reader, template, issuer, &key.PublicKey, issuerKey)
	require.NoError(t, err)
	certificate, err := x509.ParseCertificate(der)
	require.NoError(t, err)
	return certificate, key
}

func sha256Bytes(value []byte) []byte {
	hash := sha256.Sum256(value)
	return hash[:]
}

func sha512Bytes(value []byte) []byte {
	hash := sha512.Sum512(value)
	return hash[:]
}
