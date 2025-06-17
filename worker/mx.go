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

var ErrSmtpMxLookupFailed = errors.New("MX lookup failed")
var ErrSmtpMxNoRecords = errors.New("MX lookup returned no records")

var lookupMxFunc = net.LookupMX

func getMxHostsFromEmail(email string) ([]string, error) {
	domain := email[strings.Index(email, "@")+1:]

	mxCache.mu.Lock()
	defer mxCache.mu.Unlock()

	if entry, ok := mxCache.data[domain]; ok && entry.Expiry.After(time.Now()) {
		return entry.Hosts, nil
	}

	mx, err := lookupMxFunc(domain)

	if err != nil {
		return nil, fmt.Errorf("%w: %s", ErrSmtpMxLookupFailed, err)
	}

	if len(mx) == 0 {
		return nil, ErrSmtpMxNoRecords
	}

	// todo: sort by preference
	// todo: remove duplicates

	var hosts []string
	for _, mxRecord := range mx {
		host := strings.TrimSuffix(mxRecord.Host, ".")
		hosts = append(hosts, host)
	}

	mxCache.data[domain] = mxCacheEntry{
		Hosts:  hosts,
		Expiry: time.Now().Add(5 * time.Minute),
	}

	return hosts, nil

}
