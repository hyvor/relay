package main

import (
	"errors"
	"fmt"
	"net"
	"strings"
	"sync"
	"time"
)

type mxCacheEntry struct {
	Hosts  []string
	Expiry time.Time
}

type mxCacheType struct {
	data map[string]mxCacheEntry
	mu   sync.Mutex
}

var mxCache = mxCacheType{
	data: make(map[string]mxCacheEntry),
	mu:   sync.Mutex{},
}

func (m *mxCacheType) Clear() {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.data = make(map[string]mxCacheEntry)
}

var ErrSmtpMxLookupFailed = errors.New("MX lookup failed")

var lookupMxFunc = net.LookupMX
var lookupHostFunc = net.LookupHost

func getMxHostsFromDomain(domain string) ([]string, error) {

	// If the domain is already cached and not expired, return the cached hosts
	if entry, ok := mxCache.data[domain]; ok && entry.Expiry.After(time.Now()) {
		return entry.Hosts, nil
	}

	// Perform the MX lookup
	// Note: mxErr can be set even if there are MX records, so we ignore it and check the length of mx
	mx, _ := lookupMxFunc(domain)

	// if there are MX records, return those hosts
	// net.LookupMX already sorts & verifies the domains
	if len(mx) > 0 {
		hosts := getHostsFromMxRecords(mx)
		setMxCacheEntry(domain, hosts)
		return hosts, nil
	}

	// MX lookup failed or no records found
	// We will check if current domain has any A records
	ips, err := lookupHostFunc(domain)

	if err == nil && len(ips) > 0 {
		hosts := []string{domain}
		setMxCacheEntry(domain, hosts)
		return hosts, nil
	}

	return nil, fmt.Errorf("%w: %s", ErrSmtpMxLookupFailed, err)
}

func setMxCacheEntry(domain string, hosts []string) {
	mxCache.mu.Lock()
	defer mxCache.mu.Unlock()

	mxCache.data[domain] = mxCacheEntry{
		Hosts:  hosts,
		Expiry: time.Now().Add(5 * time.Minute),
	}
}

func getHostsFromMxRecords(mxRecords []*net.MX) []string {
	var hosts []string
	for _, mxRecord := range mxRecords {
		host := strings.TrimSuffix(mxRecord.Host, ".")
		hosts = append(hosts, host)
	}
	return hosts
}
