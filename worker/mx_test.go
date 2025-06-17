package main

import (
	"errors"
	"net"
	"os"
	"testing"
	"time"
)

func TestMain(m *testing.M) {
	originalLookupMxFunc := lookupMxFunc
	defer func() {
		lookupMxFunc = originalLookupMxFunc
	}()
	code := m.Run()
	os.Exit(code)
}

func TestErrorOnLookupFailed(t *testing.T) {
	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return nil, errors.New("lookup failed")
	}

	_, err := getMxHostsFromEmail("test@hyvor.com")

	if errors.Is(err, ErrSmtpMxLookupFailed) != true {
		t.Errorf("Expected error %v, got %v", ErrSmtpMxLookupFailed, err)
	}
}

func TestErrorOnNoRecords(t *testing.T) {
	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{}, nil
	}

	_, err := getMxHostsFromEmail("test@hyvor.com")
	if err != ErrSmtpMxNoRecords {
		t.Errorf("Expected error %v, got %v", ErrSmtpMxNoRecords, err)
	}
}

func TestValidMxLookupAndCache(t *testing.T) {
	lookupMxFunc = func(_ string) ([]*net.MX, error) {
		return []*net.MX{
			{Host: "mx1.hyvor.com."}, // trims the trailing dot
			{Host: "mx2.hyvor.com"},
		}, nil
	}

	hosts, err := getMxHostsFromEmail("test@hyvor.com")
	if err != nil {
		t.Errorf("Expected no error, got %v", err)
	}

	if len(hosts) != 2 {
		t.Errorf("Expected 2 MX records, got %d", len(hosts))
	}

	if hosts[0] != "mx1.hyvor.com" || hosts[1] != "mx2.hyvor.com" {
		t.Errorf("Expected MX records to be 'mx1.hyvor.com' and 'mx2.hyvor.com', got %v", hosts)
	}

	// Check cache
	if entry, ok := mxCache.data["hyvor.com"]; ok {
		if len(entry.Hosts) != 2 || entry.Hosts[0] != "mx1.hyvor.com" || entry.Hosts[1] != "mx2.hyvor.com" {
			t.Errorf("Cache entry for 'hyvor.com' does not match expected hosts: %v", entry.Hosts)
		}
		if time.Until(entry.Expiry) < 4*time.Minute || time.Until(entry.Expiry) > 5*time.Minute+10*time.Second {
			t.Errorf("expiry not set to ~5 minutes in future, got %v", entry.Expiry)
		}
	} else {
		t.Error("Cache entry for 'hyvor.com' not found")
	}
}

func TestGetHostsFromCache(t *testing.T) {
	// Pre-populate the cache
	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx1.hyvor.com", "mx2.hyvor.com"},
		Expiry: time.Now().Add(5 * time.Minute),
	}

	hosts, err := getMxHostsFromEmail("test@hyvor.com")

	if err != nil {
		t.Errorf("Expected no error, got %v", err)
	}

	if len(hosts) != 2 {
		t.Errorf("Expected 2 MX records from cache, got %d", len(hosts))
	}

	if hosts[0] != "mx1.hyvor.com" || hosts[1] != "mx2.hyvor.com" {
		t.Errorf("Expected MX records to be 'mx1.hyvor.com' and 'mx2.hyvor.com', got %v", hosts)
	}
}
