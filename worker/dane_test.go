package main

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
)

func withTlsaStub(t *testing.T, stub func(ctx context.Context, mxHost string) (tlsaLookupResult, error)) {
	original := tlsaLookupFunc
	tlsaLookupFunc = stub
	t.Cleanup(func() {
		tlsaLookupFunc = original
		cacheClearForTest()
	})
	cacheClearForTest()
}

func validTlsaRecord() *dns.TLSA {
	return &dns.TLSA{
		Usage:        3,
		Selector:     1,
		MatchingType: 1,
		Certificate:  "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa", // 64 hex chars (sha256)
	}
}

func TestGetDaneStatus_ValidWhenSecureWithRecords(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{
			Records: []*dns.TLSA{validTlsaRecord()},
			Secure:  true,
			Rcode:   dns.RcodeSuccess,
		}, nil
	})

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneValid, status)
}

func TestGetDaneStatus_InvalidWhenNotSecure(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{
			Records: []*dns.TLSA{validTlsaRecord()},
			Secure:  false,
			Rcode:   dns.RcodeSuccess,
		}, nil
	})

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneInvalid, status)
}

func TestGetDaneStatus_InvalidWhenRecordMalformed(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{
			Records: []*dns.TLSA{{Usage: 3, Selector: 1, MatchingType: 1, Certificate: "tooshort"}},
			Secure:  true,
			Rcode:   dns.RcodeSuccess,
		}, nil
	})

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneInvalid, status)
}

func TestGetDaneStatus_NotFoundWhenNoRecords(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{Records: nil, Secure: true, Rcode: dns.RcodeSuccess}, nil
	})

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneNotFound, status)
}

func TestGetDaneStatus_NotFoundOnNxdomain(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{Records: nil, Secure: true, Rcode: dns.RcodeNameError}, nil
	})

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneNotFound, status)
}

func TestGetDaneStatus_NotFoundOnQueryError(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{}, errors.New("timeout")
	})

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneNotFound, status)
}

func TestGetDaneStatus_CacheHit_SkipsLookup(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		t.Fatal("should not perform a DNS lookup on a cache hit")
		return tlsaLookupResult{}, nil
	})

	cacheSet(context.Background(), nil, "dane:mx.example.com", DaneCacheValue{Status: DaneInvalid}, daneCacheTtl)

	status, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)
	assert.Equal(t, DaneInvalid, status)
}

func TestGetDaneStatus_CachesWithOneHourTtl(t *testing.T) {
	withTlsaStub(t, func(ctx context.Context, mxHost string) (tlsaLookupResult, error) {
		return tlsaLookupResult{
			Records: []*dns.TLSA{validTlsaRecord()},
			Secure:  true,
			Rcode:   dns.RcodeSuccess,
		}, nil
	})

	_, err := getDaneStatus(context.Background(), nil, "mx.example.com")
	assert.NoError(t, err)

	entry, ok := cacheMemory.Get("dane:mx.example.com")
	assert.True(t, ok)
	assert.WithinDuration(t, time.Now().Add(daneCacheTtl), entry.ExpiresAt, 10*time.Second)
}

func TestIsValidTlsaRecord(t *testing.T) {
	assert.True(t, isValidTlsaRecord(&dns.TLSA{Usage: 3, Selector: 1, MatchingType: 1, Certificate: "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}))
	assert.False(t, isValidTlsaRecord(&dns.TLSA{Usage: 9, Selector: 1, MatchingType: 1, Certificate: "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}))
	assert.False(t, isValidTlsaRecord(&dns.TLSA{Usage: 3, Selector: 1, MatchingType: 1, Certificate: "short"}))
	assert.True(t, isValidTlsaRecord(&dns.TLSA{Usage: 0, Selector: 0, MatchingType: 0, Certificate: "aa"}))
}
