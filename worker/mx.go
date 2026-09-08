package main

import (
	"context"
	"errors"
	"fmt"
	"net"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/miekg/dns"
)

type MxRecord struct {
	Host     string `json:"host"`
	Priority int    `json:"priority"`
}

type MxCacheValue struct {
	Records []MxRecord `json:"records"`
}

// Legacy test seam retained while callers migrate to SharedCache.
type mxCacheEntry struct {
	Hosts  []string
	Expiry time.Time
}

type mxCacheType struct {
	data map[string]mxCacheEntry
	mu   sync.RWMutex
}

var mxCache = mxCacheType{data: make(map[string]mxCacheEntry)}

func (m *mxCacheType) Clear() {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.data = make(map[string]mxCacheEntry)
}

const mxCacheTTL = time.Hour

var ErrSmtpMxLookupFailed = errors.New("MX lookup failed")

var lookupDNSFunc = func(ctx context.Context, name string, recordType uint16) (DNSLookupResult, error) {
	return outboundDNSResolver.Lookup(ctx, name, recordType)
}

func getMxHostsFromDomain(domain string) ([]string, error) {
	return getMxHostsFromDomainContext(context.Background(), getProcessSharedCache(), domain)
}

func getMxHostsFromDomainContext(ctx context.Context, cache *SharedCache, domain string) ([]string, error) {
	domain = strings.TrimSuffix(strings.ToLower(strings.TrimSpace(domain)), ".")
	if domain == "" {
		return nil, fmt.Errorf("%w: empty domain", ErrSmtpMxLookupFailed)
	}
	mxCache.mu.RLock()
	legacyEntry, legacyFound := mxCache.data[domain]
	mxCache.mu.RUnlock()
	if legacyFound && legacyEntry.Expiry.After(time.Now()) {
		return legacyEntry.Hosts, nil
	}

	var cached MxCacheValue
	if found, err := cache.Get(ctx, "mx:"+domain, &cached); err == nil && found {
		return getHostsFromMxCacheValue(cached), nil
	}

	mxResult, err := lookupDNSFunc(ctx, domain, dns.TypeMX)
	if err == nil && mxResult.Message.Rcode == dns.RcodeSuccess {
		value, nullMx := getMxCacheValueFromDNS(mxResult.Message)
		if nullMx {
			return nil, fmt.Errorf("%w: null MX record for %s", ErrSmtpMxLookupFailed, domain)
		}
		if len(value.Records) > 0 {
			cacheMxValue(ctx, cache, domain, value, mxResult.TTL)
			return getHostsFromMxCacheValue(value), nil
		}
	} else if err != nil {
		// A failed MX query is not the same as an empty MX answer. Do not
		// downgrade a resolver failure into an implicit-MX lookup.
		return nil, fmt.Errorf("%w: %v", ErrSmtpMxLookupFailed, err)
	}

	addressResult, err := lookupDNSFunc(ctx, domain, dns.TypeA)
	if err != nil {
		return nil, fmt.Errorf("%w: %v", ErrSmtpMxLookupFailed, err)
	}
	if addressResult.Message.Rcode != dns.RcodeSuccess {
		return nil, fmt.Errorf("%w: no MX or address records for %s", ErrSmtpMxLookupFailed, domain)
	}
	if !hasAddressRecords(addressResult.Message) {
		addressResult, err = lookupDNSFunc(ctx, domain, dns.TypeAAAA)
		if err != nil {
			return nil, fmt.Errorf("%w: %v", ErrSmtpMxLookupFailed, err)
		}
		if addressResult.Message.Rcode != dns.RcodeSuccess || !hasAddressRecords(addressResult.Message) {
			return nil, fmt.Errorf("%w: no MX or address records for %s", ErrSmtpMxLookupFailed, domain)
		}
	}

	value := MxCacheValue{Records: []MxRecord{{Host: domain, Priority: 0}}}
	cacheMxValue(ctx, cache, domain, value, addressResult.TTL)
	return getHostsFromMxCacheValue(value), nil
}

func getMxCacheValueFromDNS(message *dns.Msg) (MxCacheValue, bool) {
	records := make([]MxRecord, 0)
	nullMx := false
	for _, answer := range message.Answer {
		mx, ok := answer.(*dns.MX)
		if !ok {
			continue
		}
		host := strings.TrimSuffix(strings.ToLower(mx.Mx), ".")
		if host == "" || host == "." {
			nullMx = true
			continue
		}
		records = append(records, MxRecord{Host: host, Priority: int(mx.Preference)})
	}
	sort.SliceStable(records, func(i, j int) bool {
		return records[i].Priority < records[j].Priority
	})
	return MxCacheValue{Records: records}, nullMx
}

func getHostsFromMxCacheValue(value MxCacheValue) []string {
	hosts := make([]string, 0, len(value.Records))
	for _, record := range value.Records {
		hosts = append(hosts, record.Host)
	}
	return hosts
}

func hasAddressRecords(message *dns.Msg) bool {
	for _, answer := range message.Answer {
		switch answer.(type) {
		case *dns.A, *dns.AAAA:
			return true
		}
	}
	return false
}

func cacheMxValue(ctx context.Context, cache *SharedCache, domain string, value MxCacheValue, ttl time.Duration) {
	if ttl <= 0 {
		return
	}
	if ttl > mxCacheTTL {
		ttl = mxCacheTTL
	}
	_ = cache.Set(ctx, "mx:"+domain, value, ttl)
}

// Kept for callers that need to convert resolver results into SMTP hosts.
func getHostsFromMxRecords(mxRecords []*net.MX) []string {
	value := MxCacheValue{Records: make([]MxRecord, 0, len(mxRecords))}
	for _, mxRecord := range mxRecords {
		value.Records = append(value.Records, MxRecord{
			Host:     strings.TrimSuffix(strings.ToLower(mxRecord.Host), "."),
			Priority: int(mxRecord.Pref),
		})
	}
	return getHostsFromMxCacheValue(value)
}
