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

func withDNSLookupStub(t *testing.T, stub func(context.Context, string, uint16) (DNSLookupResult, error)) {
	original := lookupDNSFunc
	lookupDNSFunc = stub
	t.Cleanup(func() {
		lookupDNSFunc = original
	})
}

func mxMessage(records ...dns.RR) *dns.Msg {
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
			return DNSLookupResult{Message: mxMessage(), TTL: time.Minute}, nil
		}
		return DNSLookupResult{
			Message: mxMessage(&dns.A{Hdr: dns.RR_Header{Rrtype: dns.TypeA}, A: []byte{1, 1, 1, 1}}),
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
			Message: mxMessage(
				&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "mx1.hyvor.com.", Preference: 10},
				&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "mx2.hyvor.com.", Preference: 20},
			),
			TTL: 2 * time.Hour,
		}, nil
	})

	cache := NewSharedCache(nil)
	t.Cleanup(cache.Close)
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
	t.Cleanup(cache.Close)
	cacheMxValue(context.Background(), cache, "hyvor.com", MxCacheValue{Records: []MxRecord{{Host: "mx.hyvor.com"}}}, 2*time.Hour)

	item := cache.memory.Get("mx:hyvor.com")
	require.NotNil(t, item)
	assert.WithinDuration(t, time.Now().Add(time.Hour), item.ExpiresAt(), 2*time.Second)
}

func TestMxLookupSortsByPriority(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeMX, recordType)
		return DNSLookupResult{Message: mxMessage(
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
		return DNSLookupResult{Message: mxMessage(
			&dns.MX{Hdr: dns.RR_Header{Rrtype: dns.TypeMX}, Mx: "."},
		), TTL: time.Minute}, nil
	})

	_, err := getMxHostsFromDomainContext(context.Background(), NewSharedCache(nil), "example.com")
	assert.ErrorIs(t, err, ErrSmtpMxLookupFailed)
}
