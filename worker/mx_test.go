package main

import (
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

func TestErrorOnLookupBothMxAndHostLookupsFail(t *testing.T) {
	mxCache.Clear()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return nil, errors.New("lookup failed")
	}

	lookupHostFunc = func(_ string) ([]string, error) {
		return nil, errors.New("lookup failed")
	}

	_, err := getMxHostsFromEmail("test@hyvor.com")

	assert.Error(t, err)
	assert.True(t, errors.Is(err, ErrSmtpMxLookupFailed))
}

func TestReturnsCurrentHostOnMxLookupFailure(t *testing.T) {
	mxCache.Clear()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return nil, errors.New("lookup failed")
	}

	lookupHostFunc = func(_ string) ([]string, error) {
		return []string{"1.1.1.1"}, nil
	}

	hosts, err := getMxHostsFromEmail("test@hyvor.com")

	assert.NoError(t, err)
	assert.Equal(t, []string{"hyvor.com"}, hosts)

}

func TestCurrentDomainOnNoMxHosts(t *testing.T) {
	mxCache.Clear()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{}, nil
	}

	lookupHostFunc = func(_ string) ([]string, error) {
		return []string{"1.1.1.1"}, nil
	}

	hosts, err := getMxHostsFromEmail("test@hyvor.com")
	assert.NoError(t, err)
	assert.Equal(t, []string{"hyvor.com"}, hosts)
}

func TestValidMxLookupAndCache(t *testing.T) {

	mxCache.Clear()

	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{
			{Host: "mx1.hyvor.com."}, // trims the trailing dot
			{Host: "mx2.hyvor.com"},
		}, nil
	}

	hosts, err := getMxHostsFromEmail("test@hyvor.com")
	assert.NoError(t, err)
	assert.Equal(t, 2, len(hosts))

	assert.Equal(t, "mx1.hyvor.com", hosts[0])
	assert.Equal(t, "mx2.hyvor.com", hosts[1])

	entry, ok := mxCache.data["hyvor.com"]
	assert.True(t, ok)
	assert.Equal(t, []string{"mx1.hyvor.com", "mx2.hyvor.com"}, entry.Hosts)
	assert.WithinDuration(t, time.Now().Add(5*time.Minute), entry.Expiry, 10*time.Second)

}

func TestGetHostsFromCache(t *testing.T) {

	mxCache.Clear()

	// Pre-populate the cache
	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx1.hyvor.com", "mx2.hyvor.com"},
		Expiry: time.Now().Add(5 * time.Minute),
	}

	hosts, err := getMxHostsFromEmail("test@hyvor.com")
	assert.NoError(t, err)
	assert.Equal(t, []string{"mx1.hyvor.com", "mx2.hyvor.com"}, hosts)

}
