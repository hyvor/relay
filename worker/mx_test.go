package main

import (
	"context"
	"errors"
	"net"
	"os"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestMain(m *testing.M) {
	originalLookupMxFunc := lookupMxFunc
	originalLookupHostFunc := lookupHostFunc
	defer func() {
		lookupMxFunc = originalLookupMxFunc
		lookupHostFunc = originalLookupHostFunc
	}()
	code := m.Run()
	os.Exit(code)
}

// primeMxCacheForTest seeds the memory tier of the shared cache with an MX
// cache entry, for tests that want to assert cache-hit behavior without
// going through a DNS lookup.
func primeMxCacheForTest(domain string, hosts []string) {
	records := make([]MxRecord, 0, len(hosts))
	for i, host := range hosts {
		records = append(records, MxRecord{Host: host, Priority: i * 10})
	}
	cacheSet(context.Background(), nil, "mx:"+domain, MxCacheValue{Records: records}, mxCacheTtl)
}

func TestErrorOnLookupBothMxAndHostLookupsFail(t *testing.T) {
	cacheClearForTest()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return nil, errors.New("lookup failed")
	}

	lookupHostFunc = func(_ string) ([]string, error) {
		return nil, errors.New("lookup failed")
	}

	_, err := getMxHostsFromDomain(context.Background(), nil, "hyvor.com")

	assert.Error(t, err)
	assert.True(t, errors.Is(err, ErrSmtpMxLookupFailed))
}

func TestReturnsCurrentHostOnMxLookupFailure(t *testing.T) {
	cacheClearForTest()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return nil, errors.New("lookup failed")
	}

	lookupHostFunc = func(_ string) ([]string, error) {
		return []string{"1.1.1.1"}, nil
	}

	hosts, err := getMxHostsFromDomain(context.Background(), nil, "hyvor.com")

	assert.NoError(t, err)
	assert.Equal(t, []string{"hyvor.com"}, hosts)

}

func TestCurrentDomainOnNoMxHosts(t *testing.T) {
	cacheClearForTest()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{}, nil
	}

	lookupHostFunc = func(_ string) ([]string, error) {
		return []string{"1.1.1.1"}, nil
	}

	hosts, err := getMxHostsFromDomain(context.Background(), nil, "hyvor.com")
	assert.NoError(t, err)
	assert.Equal(t, []string{"hyvor.com"}, hosts)
}

func TestValidMxLookupAndCache(t *testing.T) {

	cacheClearForTest()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{
			{Host: "mx1.hyvor.com.", Pref: 10}, // trims the trailing dot
			{Host: "mx2.hyvor.com", Pref: 20},
		}, nil
	}

	hosts, err := getMxHostsFromDomain(context.Background(), nil, "hyvor.com")
	assert.NoError(t, err)
	assert.Equal(t, 2, len(hosts))

	assert.Equal(t, "mx1.hyvor.com", hosts[0])
	assert.Equal(t, "mx2.hyvor.com", hosts[1])

	var cached MxCacheValue
	found, err := cacheGet(context.Background(), nil, "mx:hyvor.com", &cached)
	assert.NoError(t, err)
	assert.True(t, found)
	assert.Equal(t, []MxRecord{
		{Host: "mx1.hyvor.com", Priority: 10},
		{Host: "mx2.hyvor.com", Priority: 20},
	}, cached.Records)

	entry, ok := cacheMemory.Get("mx:hyvor.com")
	assert.True(t, ok)
	assert.WithinDuration(t, time.Now().Add(mxCacheTtl), entry.ExpiresAt, 10*time.Second)

}

func TestGetHostsFromCache(t *testing.T) {

	cacheClearForTest()

	primeMxCacheForTest("hyvor.com", []string{"mx1.hyvor.com", "mx2.hyvor.com"})

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		t.Fatal("should not perform a DNS lookup on a cache hit")
		return nil, nil
	}

	hosts, err := getMxHostsFromDomain(context.Background(), nil, "hyvor.com")
	assert.NoError(t, err)
	assert.Equal(t, []string{"mx1.hyvor.com", "mx2.hyvor.com"}, hosts)

}
