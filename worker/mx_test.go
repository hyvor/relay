package main

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func getMxHostsFromDomainContext(ctx context.Context, cache *SharedCache, domain string) ([]string, error) {
	value, err := getMxValueFromDomainContext(ctx, cache, domain)
	if err != nil {
		return nil, err
	}
	return getHostsFromMxCacheValue(value), nil
}

func withDNSLookupStub(t *testing.T, stub func(context.Context, string, uint16) (DNSLookupResult, error)) {
	original := lookupDNSFunc
	originalTLSA := lookupTLSAFunc
	lookupDNSFunc = stub
	lookupTLSAFunc = lookupTLSA
	t.Cleanup(func() {
		lookupDNSFunc = original
		lookupTLSAFunc = originalTLSA
	})
}

func mxMessage(domain string, records ...dns.RR) *dns.Msg {
	for _, record := range records {
		record.Header().Name = dns.Fqdn(domain)
		if record.Header().Class == 0 {
			record.Header().Class = dns.ClassINET
		}
	}
	return &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess}, Answer: records}
}

func TestErrorOnLookupFailure(t *testing.T) {
	withDNSLookupStub(t, func(context.Context, string, uint16) (DNSLookupResult, error) {
		return DNSLookupResult{}, errors.New("lookup failed")
	})

	_, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "hyvor.com")
	assert.ErrorIs(t, err, ErrSmtpMxLookupFailed)
}

func TestReturnsCurrentHostWhenMxIsAbsent(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		if recordType == dns.TypeMX {
			return DNSLookupResult{Message: mxMessage("hyvor.com"), TTL: time.Minute}, nil
		}
		return DNSLookupResult{
			Message: mxMessage("hyvor.com", &dns.A{Hdr: dns.RR_Header{Rrtype: dns.TypeA}, A: []byte{1, 1, 1, 1}}),
			TTL:     time.Minute,
		}, nil
	})

	hosts, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "hyvor.com")
	require.NoError(t, err)
	assert.Equal(t, []string{"hyvor.com"}, hosts)
}

func TestValidMxLookupAndCache(t *testing.T) {
	lookups := 0
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		lookups++
		assert.Equal(t, dns.TypeMX, recordType)
		return DNSLookupResult{
			Message: mxMessage("hyvor.com",
				&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "mx1.hyvor.com.", Preference: 10},
				&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "mx2.hyvor.com.", Preference: 20},
			),
			TTL: 2 * time.Hour,
		}, nil
	})

	cache := NewSharedCache(nil)
	hosts, err := getMxHostsFromDomainContext(context.Background(), cache, "HYVOR.COM.")
	require.NoError(t, err)
	assert.Equal(t, []string{"mx1.hyvor.com", "mx2.hyvor.com"}, hosts)

	hosts, err = getMxHostsFromDomainContext(context.Background(), cache, "hyvor.com")
	require.NoError(t, err)
	assert.Equal(t, []string{"mx1.hyvor.com", "mx2.hyvor.com"}, hosts)
	assert.Equal(t, 1, lookups)
}

func TestMxCacheUsesOneHourMaximumTTL(t *testing.T) {
	cache := NewSharedCache(nil)
	cacheMxValue(context.Background(), cache, "hyvor.com", MxCacheValue{Records: []MxRecord{{Host: "mx.hyvor.com"}}}, 2*time.Hour)

	item := cache.memory.Get(dnsCacheKey("mx", "hyvor.com"))
	require.NotNil(t, item)
	assert.WithinDuration(t, time.Now().Add(time.Hour), item.ExpiresAt(), 2*time.Second)
}

func TestMxLookupSortsByPriority(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeMX, recordType)
		return DNSLookupResult{Message: mxMessage("example.com",
			&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "slow.example.", Preference: 50},
			&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "fast.example.", Preference: 10},
		), TTL: time.Minute}, nil
	})

	hosts, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	require.NoError(t, err)
	assert.Equal(t, []string{"fast.example", "slow.example"}, hosts)
}

func TestNullMxIsRejected(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeMX, recordType)
		return DNSLookupResult{Message: mxMessage("example.com",
			&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "."},
		), TTL: time.Minute}, nil
	})

	_, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	assert.ErrorIs(t, err, ErrSmtpMxPermanent)
}

func TestMxDnsFailureDoesNotFallbackToAddress(t *testing.T) {
	lookups := 0
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		lookups++
		return DNSLookupResult{Message: &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeServerFailure}}}, nil
	})

	_, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	assert.ErrorIs(t, err, ErrSmtpMxLookupFailed)
	assert.Equal(t, 1, lookups)
}

func TestMxLookupFallsBackToAAAA(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		switch recordType {
		case dns.TypeMX:
			return DNSLookupResult{Message: mxMessage("example.com"), TTL: time.Minute}, nil
		case dns.TypeA:
			return DNSLookupResult{Message: &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess}}}, nil
		case dns.TypeAAAA:
			return DNSLookupResult{Message: mxMessage("example.com", &dns.AAAA{
				Hdr:  dns.RR_Header{Rrtype: dns.TypeAAAA},
				AAAA: []byte{0x20, 0x01},
			}), TTL: time.Minute}, nil
		default:
			t.Fatalf("unexpected record type %d", recordType)
			return DNSLookupResult{}, nil
		}
	})

	hosts, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	require.NoError(t, err)
	assert.Equal(t, []string{"example.com"}, hosts)
}

func TestMxLookupFollowsCname(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeMX, recordType)
		return DNSLookupResult{Message: &dns.Msg{
			MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess},
			Answer: []dns.RR{
				&dns.CNAME{Hdr: dns.RR_Header{Name: "example.com.", Rrtype: dns.TypeCNAME, Class: dns.ClassINET}, Target: "canonical.example."},
				&dns.MX{Hdr: dns.RR_Header{Name: "canonical.example.", Rrtype: dns.TypeMX, Class: dns.ClassINET}, Mx: "mx.example.", Preference: 10},
			},
		}, TTL: time.Minute}, nil
	})

	hosts, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	require.NoError(t, err)
	assert.Equal(t, []string{"mx.example"}, hosts)
}

func TestMxLookupRejectsIncompleteCnameChain(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeMX, recordType, "must not fall back to an address lookup")
		return DNSLookupResult{Message: &dns.Msg{
			MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess},
			Answer: []dns.RR{
				&dns.CNAME{Hdr: dns.RR_Header{Name: "example.com.", Rrtype: dns.TypeCNAME, Class: dns.ClassINET}, Target: "canonical.example."},
			},
		}, TTL: time.Minute}, nil
	})

	_, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	assert.ErrorIs(t, err, ErrSmtpMxLookupFailed)
}
