package main

import (
	"errors"
	"fmt"
	"net"
	"slices"
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
var ErrSmtpMxNoRecords = errors.New("MX lookup returned no records")

var lookupMxFunc = net.LookupMX

const TESTING_DOMAIN = "hyvor.local.testing"

func getMxHostsFromEmail(email string) ([]string, error) {
	domain := email[strings.Index(email, "@")+1:]

	if domain == TESTING_DOMAIN {
		return []string{"hyvor-service-mailpit"}, nil
	}

	mxCache.mu.Lock()
	defer mxCache.mu.Unlock()

	if entry, ok := mxCache.data[domain]; ok && entry.Expiry.After(time.Now()) {
		return entry.Hosts, nil
	}

	mx, err := lookupMxFunc(domain)

	if err != nil {
		return nil, fmt.Errorf("%w: %s", ErrSmtpMxLookupFailed, err)
	}

	slices.SortFunc(mx, func(a, b *net.MX) int {
		if a.Pref < b.Pref {
			return -1
		} else if a.Pref > b.Pref {
			return 1
		}
		return 0
	})

	var hosts []string
	for _, mxRecord := range mx {
		host := strings.TrimSuffix(mxRecord.Host, ".")

		// skip empty hosts
		if host == "" {
			continue
		}

		// skip duplicates
		if slices.Contains(hosts, host) {
			continue
		}

		hosts = append(hosts, host)
	}

	if len(hosts) == 0 {
		return nil, ErrSmtpMxNoRecords
	}

	mxCache.data[domain] = mxCacheEntry{
		Hosts:  hosts,
		Expiry: time.Now().Add(5 * time.Minute),
	}

	return hosts, nil
}
