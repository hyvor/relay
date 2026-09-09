package main

import (
	"context"
	"errors"
	"fmt"
	"sort"
	"strings"
	"time"

	"github.com/miekg/dns"
)

type MxRecord struct {
	Host     string `json:"host"`
	Priority int    `json:"priority"`
}

type MxCacheValue struct {
	Records []MxRecord `json:"records"`
	Secure  bool       `json:"secure"`
}

const mxCacheTTL = time.Hour

var ErrSmtpMxLookupFailed = errors.New("MX lookup failed")
var ErrSmtpMxPermanent = errors.New("permanent MX failure")

var lookupDNSFunc = func(ctx context.Context, name string, recordType uint16) (DNSLookupResult, error) {
	return outboundDNSResolver.Lookup(ctx, name, recordType)
}

func getMxHostsFromDomainContext(ctx context.Context, cache *SharedCache, domain string) ([]string, error) {
	value, err := getMxValueFromDomainContext(ctx, cache, domain)
	if err != nil {
		return nil, err
	}
	return getHostsFromMxCacheValue(value), nil
}

func getMxValueFromDomainContext(ctx context.Context, cache *SharedCache, domain string) (MxCacheValue, error) {
	domain = strings.TrimSuffix(strings.ToLower(strings.TrimSpace(domain)), ".")
	if domain == "" {
		return MxCacheValue{}, fmt.Errorf("%w: empty domain", ErrSmtpMxLookupFailed)
	}
	var cached MxCacheValue
	cacheKey := dnsCacheKey("mx", domain)
	if found, err := cache.Get(ctx, cacheKey, &cached); err == nil && found {
		if validMxCacheValue(cached) {
			return cached, nil
		}
		_ = cache.Delete(ctx, cacheKey)
	}

	mxResult, err := lookupDNSFunc(ctx, domain, dns.TypeMX)
	if err != nil {
		return MxCacheValue{}, fmt.Errorf("%w: %v", ErrSmtpMxLookupFailed, err)
	}
	if mxResult.Message == nil {
		return MxCacheValue{}, fmt.Errorf("%w: MX DNS response was empty", ErrSmtpMxLookupFailed)
	}
	if mxResult.Message.Rcode != dns.RcodeSuccess {
		if mxResult.Message.Rcode == dns.RcodeNameError {
			return MxCacheValue{}, fmt.Errorf("%w: %w for %s", ErrSmtpMxPermanent, ErrSmtpMxLookupFailed, domain)
		}
		return MxCacheValue{}, fmt.Errorf("%w: MX DNS response code %s", ErrSmtpMxLookupFailed, dns.RcodeToString[mxResult.Message.Rcode])
	}
	value, nullMx, complete := getMxCacheValueFromDNS(mxResult.Message, domain, mxResult.Secure)
	if !complete {
		return MxCacheValue{}, fmt.Errorf("%w: incomplete or looping MX alias chain for %s", ErrSmtpMxLookupFailed, domain)
	}
	if nullMx {
		return MxCacheValue{}, fmt.Errorf("%w: %w: null MX record for %s", ErrSmtpMxPermanent, ErrSmtpMxLookupFailed, domain)
	}
	if len(value.Records) > 0 {
		cacheMxValue(ctx, cache, domain, value, mxResult.TTL)
		return value, nil
	}

	addressResult, err := lookupDNSFunc(ctx, domain, dns.TypeA)
	if err != nil {
		return MxCacheValue{}, fmt.Errorf("%w: %v", ErrSmtpMxLookupFailed, err)
	}
	if addressResult.Message == nil || addressResult.Message.Rcode != dns.RcodeSuccess {
		return MxCacheValue{}, fmt.Errorf("%w: A DNS response did not succeed", ErrSmtpMxLookupFailed)
	}
	addressFound, addressComplete, addressHost := hasAddressRecords(addressResult.Message, domain)
	if !addressComplete {
		return MxCacheValue{}, fmt.Errorf("%w: incomplete or looping address alias chain for %s", ErrSmtpMxLookupFailed, domain)
	}
	if !addressFound {
		addressResult, err = lookupDNSFunc(ctx, domain, dns.TypeAAAA)
		if err != nil {
			return MxCacheValue{}, fmt.Errorf("%w: %v", ErrSmtpMxLookupFailed, err)
		}
		if addressResult.Message == nil || addressResult.Message.Rcode != dns.RcodeSuccess {
			return MxCacheValue{}, fmt.Errorf("%w: no MX or address records for %s", ErrSmtpMxLookupFailed, domain)
		}
		addressFound, addressComplete, addressHost = hasAddressRecords(addressResult.Message, domain)
		if !addressComplete || !addressFound {
			return MxCacheValue{}, fmt.Errorf("%w: no MX or address records for %s", ErrSmtpMxLookupFailed, domain)
		}
	}

	value = MxCacheValue{Records: []MxRecord{{Host: addressHost, Priority: 0}}, Secure: mxResult.Secure}
	ttl := mxResult.TTL
	if mxResult.TTL <= 0 || addressResult.TTL <= 0 {
		ttl = 0
	} else if addressResult.TTL < ttl {
		ttl = addressResult.TTL
	}
	cacheMxValue(ctx, cache, domain, value, ttl)
	return value, nil
}

func getMxCacheValueFromDNS(message *dns.Msg, domain string, secure bool) (MxCacheValue, bool, bool) {
	records := make([]MxRecord, 0)
	nullMx := false
	owner := dns.Fqdn(domain)
	followedAlias := false
	for hops := 0; hops < 8; hops++ {
		foundOwner := false
		for _, answer := range message.Answer {
			mx, ok := answer.(*dns.MX)
			if !ok || mx.Hdr.Class != dns.ClassINET || !strings.EqualFold(mx.Hdr.Name, owner) {
				continue
			}
			foundOwner = true
			host := strings.TrimSuffix(strings.ToLower(mx.Mx), ".")
			if host == "" || host == "." {
				nullMx = true
				continue
			}
			records = append(records, MxRecord{Host: host, Priority: int(mx.Preference)})
		}
		if foundOwner {
			sort.SliceStable(records, func(i, j int) bool {
				return records[i].Priority < records[j].Priority
			})
			return MxCacheValue{Records: records, Secure: secure}, nullMx, true
		}
		alias, ok := dnsAliasTarget(message, owner)
		if !ok {
			return MxCacheValue{Records: records, Secure: secure}, nullMx, !followedAlias || !hasAliasContinuation(message)
		}
		followedAlias = true
		owner = alias
	}
	sort.SliceStable(records, func(i, j int) bool {
		return records[i].Priority < records[j].Priority
	})
	return MxCacheValue{Records: records, Secure: secure}, nullMx, false
}

func validMxCacheValue(value MxCacheValue) bool {
	if len(value.Records) == 0 {
		return false
	}
	for _, record := range value.Records {
		if record.Host == "" || record.Host == "." || record.Priority < 0 || record.Priority > 65535 ||
			normalizeDNSHost(record.Host) != record.Host || strings.ContainsAny(record.Host, " /:@") {
			return false
		}
	}
	return true
}

func getHostsFromMxCacheValue(value MxCacheValue) []string {
	hosts := make([]string, 0, len(value.Records))
	for _, record := range value.Records {
		hosts = append(hosts, record.Host)
	}
	return hosts
}

func hasAddressRecords(message *dns.Msg, domain string) (bool, bool, string) {
	owner := dns.Fqdn(domain)
	for hops := 0; hops < 8; hops++ {
		for _, answer := range message.Answer {
			switch record := answer.(type) {
			case *dns.A:
				if record.Hdr.Class == dns.ClassINET && strings.EqualFold(record.Hdr.Name, owner) {
					return true, true, normalizeDNSHost(owner)
				}
			case *dns.AAAA:
				if record.Hdr.Class == dns.ClassINET && strings.EqualFold(record.Hdr.Name, owner) {
					return true, true, normalizeDNSHost(owner)
				}
			}
		}
		alias, ok := dnsAliasTarget(message, owner)
		if !ok {
			return false, true, normalizeDNSHost(owner)
		}
		owner = alias
	}
	return false, false, ""
}

func hasAliasContinuation(message *dns.Msg) bool {
	for _, record := range message.Ns {
		if _, ok := record.(*dns.SOA); ok {
			return false
		}
	}
	return true
}

func dnsAliasTarget(message *dns.Msg, owner string) (string, bool) {
	for _, answer := range message.Answer {
		alias, ok := answer.(*dns.CNAME)
		if ok && alias.Hdr.Class == dns.ClassINET && strings.EqualFold(alias.Hdr.Name, owner) {
			return dns.Fqdn(alias.Target), true
		}
	}
	return "", false
}

func cacheMxValue(ctx context.Context, cache *SharedCache, domain string, value MxCacheValue, ttl time.Duration) {
	if ttl <= 0 {
		return
	}
	if ttl > mxCacheTTL {
		ttl = mxCacheTTL
	}
	_ = cache.Set(ctx, dnsCacheKey("mx", domain), value, ttl)
}
