package main

import (
	"context"
	"strings"
	"testing"
	"time"

	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func tlsaMessage(owner string, records ...dns.RR) *dns.Msg {
	for _, record := range records {
		record.Header().Name = dns.Fqdn(owner)
		if record.Header().Class == 0 {
			record.Header().Class = dns.ClassINET
		}
	}
	return &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess}, Answer: records}
}

func validTLSARecord() *dns.TLSA {
	return &dns.TLSA{
		Hdr:          dns.RR_Header{Rrtype: dns.TypeTLSA},
		Usage:        3,
		Selector:     1,
		MatchingType: 1,
		Certificate:  strings.Repeat("ab", 32),
	}
}

func TestLookupTLSASecureRecordsAndCache(t *testing.T) {
	lookups := 0
	withDNSLookupStub(t, func(_ context.Context, name string, recordType uint16) (DNSLookupResult, error) {
		lookups++
		assert.Equal(t, "_25._tcp.mx.example.com", name)
		assert.Equal(t, dns.TypeTLSA, recordType)
		return DNSLookupResult{
			Message: tlsaMessage(name, validTLSARecord()),
			Secure:  true,
			TTL:     2 * time.Hour,
		}, nil
	})

	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
	result, err := lookupTLSA(context.Background(), cache, "MX.Example.Com.")
	require.NoError(t, err)
	assert.Equal(t, TLSAStateSecureRecords, result.State)
	assert.Len(t, result.Records, 1)

	result, err = lookupTLSA(context.Background(), cache, "mx.example.com")
	require.NoError(t, err)
	assert.Equal(t, TLSAStateSecureRecords, result.State)
	assert.Equal(t, 1, lookups)
}

func TestLookupTLSASecureAbsenceIsDistinguished(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeTLSA, recordType)
		return DNSLookupResult{
			Message: &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess}},
			Secure:  true,
			TTL:     time.Minute,
		}, nil
	})

	result, err := lookupTLSA(context.Background(), NewSharedCache(nil), "mx.example.com")
	require.NoError(t, err)
	assert.Equal(t, TLSAStateSecureAbsent, result.State)
	assert.Empty(t, result.Records)
}

func TestLookupTLSAInsecureRecordsAreNotDANEUsable(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, _ uint16) (DNSLookupResult, error) {
		return DNSLookupResult{Message: tlsaMessage("_25._tcp.mx.example.com", validTLSARecord()), TTL: time.Minute}, nil
	})

	result, err := lookupTLSA(context.Background(), NewSharedCache(nil), "mx.example.com")
	require.NoError(t, err)
	assert.Equal(t, TLSAStateInsecure, result.State)
	assert.Len(t, result.Records, 1)
}

func TestLookupTLSAUnusableRecordsAreNotAbsence(t *testing.T) {
	tests := []struct {
		name   string
		record *dns.TLSA
	}{
		{
			name:   "association is not hex",
			record: &dns.TLSA{Usage: 3, Selector: 1, MatchingType: 1, Certificate: "not-hex"},
		},
		{
			name:   "unsupported certificate usage",
			record: &dns.TLSA{Usage: 1, Selector: 1, MatchingType: 1, Certificate: strings.Repeat("ab", 32)},
		},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			test.record.Hdr = dns.RR_Header{Rrtype: dns.TypeTLSA}
			withDNSLookupStub(t, func(_ context.Context, name string, _ uint16) (DNSLookupResult, error) {
				return DNSLookupResult{
					Message: tlsaMessage(name, test.record),
					Secure:  true,
					TTL:     time.Minute,
				}, nil
			})

			result, err := lookupTLSA(context.Background(), NewSharedCache(nil), "mx.example.com")
			require.NoError(t, err)
			assert.Equal(t, TLSAStateSecureUnusable, result.State)
			assert.Empty(t, result.Records)
		})
	}
}

func TestLookupTLSAFollowsCname(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, name string, _ uint16) (DNSLookupResult, error) {
		return DNSLookupResult{
			Message: &dns.Msg{
				MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess},
				Answer: []dns.RR{
					&dns.CNAME{Hdr: dns.RR_Header{Name: dns.Fqdn(name), Rrtype: dns.TypeCNAME, Class: dns.ClassINET}, Target: "shared.example."},
					&dns.TLSA{Hdr: dns.RR_Header{Name: "shared.example.", Rrtype: dns.TypeTLSA, Class: dns.ClassINET}, Usage: 3, Selector: 1, MatchingType: 1, Certificate: strings.Repeat("ab", 32)},
				},
			},
			Secure: true,
			TTL:    time.Minute,
		}, nil
	})

	result, err := lookupTLSA(context.Background(), NewSharedCache(nil), "mx.example.com")
	require.NoError(t, err)
	assert.Equal(t, TLSAStateSecureRecords, result.State)
	assert.Len(t, result.Records, 1)
}

func TestLookupTLSARejectsTransientDnsFailure(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, _ uint16) (DNSLookupResult, error) {
		return DNSLookupResult{Message: &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeServerFailure}}}, nil
	})

	_, err := lookupTLSA(context.Background(), NewSharedCache(nil), "mx.example.com")
	assert.ErrorIs(t, err, ErrTLSALookup)
}
