package main

import (
	"context"
	"database/sql"
	"os"
	"time"

	"github.com/miekg/dns"
)

// DANE (RFC 7672): a TLSA record published at _<port>._tcp.<mx-host> pins
// the certificate(s) the MX host is allowed to present. We don't validate a
// specific connection's certificate against the record (that would require
// threading TLSA data into the TLS handshake itself) - we only determine
// whether STARTTLS must be mandatory for this host, which only needs to
// know whether a (trustworthy) TLSA record exists at all.
//
// DNSSEC validation itself is delegated to a trusted upstream resolver: we
// query with the DO bit set and trust its Authenticated Data (AD) flag,
// rather than validating the RRSIG/DNSKEY/DS chain ourselves.

type DaneStatus string

const (
	DaneValid    DaneStatus = "valid"
	DaneInvalid  DaneStatus = "invalid"
	DaneNotFound DaneStatus = "not_found"
)

type DaneCacheValue struct {
	Status DaneStatus `json:"status"`
}

const daneCacheTtl = 1 * time.Hour

const defaultDaneResolver = "1.1.1.1:53"

func getDaneResolver() string {
	if resolver := os.Getenv("DANE_RESOLVER"); resolver != "" {
		return resolver
	}
	return defaultDaneResolver
}

type tlsaLookupResult struct {
	Records []*dns.TLSA
	// Secure reports whether the resolver marked the response as DNSSEC
	// authenticated (the AD bit).
	Secure bool
	Rcode  int
}

var tlsaLookupFunc = tlsaLookupOverDns

func getDaneStatus(ctx context.Context, conn *sql.DB, mxHost string) (DaneStatus, error) {

	cacheKey := "dane:" + mxHost

	var cached DaneCacheValue
	if found, _ := cacheGet(ctx, conn, cacheKey, &cached); found {
		return cached.Status, nil
	}

	status := determineDaneStatus(ctx, mxHost)

	cacheSet(ctx, conn, cacheKey, DaneCacheValue{Status: status}, daneCacheTtl)

	return status, nil

}

func determineDaneStatus(ctx context.Context, mxHost string) DaneStatus {

	result, err := tlsaLookupFunc(ctx, mxHost)

	if err != nil || result.Rcode != dns.RcodeSuccess || len(result.Records) == 0 {
		return DaneNotFound
	}

	if !result.Secure {
		return DaneInvalid
	}

	for _, record := range result.Records {
		if !isValidTlsaRecord(record) {
			return DaneInvalid
		}
	}

	return DaneValid

}

// isValidTlsaRecord does a basic sanity check of the record's fields, per
// RFC 6698: known usage/selector/matching-type values, and a certificate
// association length consistent with the matching type.
func isValidTlsaRecord(record *dns.TLSA) bool {

	if record.Usage > 3 || record.Selector > 1 || record.MatchingType > 2 {
		return false
	}

	certLen := len(record.Certificate)

	switch record.MatchingType {
	case 1: // SHA-256
		return certLen == 64
	case 2: // SHA-512
		return certLen == 128
	default: // 0: full certificate/SPKI, variable length
		return certLen > 0
	}

}

func tlsaLookupOverDns(ctx context.Context, mxHost string) (tlsaLookupResult, error) {

	msg := new(dns.Msg)
	msg.SetQuestion(dns.Fqdn("_25._tcp."+mxHost), dns.TypeTLSA)
	msg.SetEdns0(4096, true)

	client := new(dns.Client)
	client.Timeout = 5 * time.Second

	resp, _, err := client.ExchangeContext(ctx, msg, getDaneResolver())
	if err != nil {
		return tlsaLookupResult{}, err
	}

	result := tlsaLookupResult{
		Rcode:  resp.Rcode,
		Secure: resp.AuthenticatedData,
	}

	for _, answer := range resp.Answer {
		if tlsa, ok := answer.(*dns.TLSA); ok {
			result.Records = append(result.Records, tlsa)
		}
	}

	return result, nil

}
