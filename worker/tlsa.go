package main

import (
	"context"
	"encoding/hex"
	"errors"
	"fmt"
	"strings"
	"time"

	"github.com/miekg/dns"
)

const tlsaCacheTTL = time.Hour

var ErrTLSALookup = errors.New("TLSA lookup failed")

type TLSAState string

const (
	TLSAStateSecureRecords  TLSAState = "secure_records"
	TLSAStateSecureAbsent   TLSAState = "secure_absent"
	TLSAStateSecureUnusable TLSAState = "secure_unusable"
	TLSAStateInsecure       TLSAState = "insecure"
)

type TLSARecord struct {
	CertificateUsage       uint8  `json:"certificate_usage"`
	Selector               uint8  `json:"selector"`
	MatchingType           uint8  `json:"matching_type"`
	CertificateAssociation string `json:"certificate_association_data"`
}

type TLSACacheValue struct {
	State   TLSAState    `json:"state"`
	Records []TLSARecord `json:"records"`
}

type TLSAResult struct {
	State   TLSAState
	Records []TLSARecord
}

func lookupTLSA(ctx context.Context, cache *SharedCache, host string) (TLSAResult, error) {
	host = normalizeDNSHost(host)
	if host == "" {
		return TLSAResult{}, fmt.Errorf("%w: empty host", ErrTLSALookup)
	}

	cacheKey := "tlsa:v1:_25._tcp." + host
	var cached TLSACacheValue
	if found, err := cache.Get(ctx, cacheKey, &cached); err == nil && found {
		if validTLSACacheValue(cached) {
			return TLSAResult{State: cached.State, Records: cached.Records}, nil
		}
		_ = cache.Delete(ctx, cacheKey)
	}

	name := "_25._tcp." + host
	result, err := lookupDNSFunc(ctx, name, dns.TypeTLSA)
	if err != nil {
		return TLSAResult{}, fmt.Errorf("%w: %v", ErrTLSALookup, err)
	}
	if result.Message == nil {
		return TLSAResult{}, fmt.Errorf("%w: empty DNS response", ErrTLSALookup)
	}
	if result.Message.Rcode != dns.RcodeSuccess && result.Message.Rcode != dns.RcodeNameError {
		return TLSAResult{}, fmt.Errorf("%w: DNS response code %s", ErrTLSALookup, dns.RcodeToString[result.Message.Rcode])
	}

	value := TLSACacheValue{State: TLSAStateInsecure}
	if result.Secure {
		value.State = TLSAStateSecureAbsent
	}
	if result.Message.Rcode == dns.RcodeSuccess {
		var invalid bool
		value.Records, invalid = getTLSARecordsFromDNS(result.Message, name)
		if len(value.Records) > 0 && result.Secure {
			value.State = TLSAStateSecureRecords
		} else if invalid && result.Secure {
			value.State = TLSAStateSecureUnusable
		}
	}

	cacheTLSAValue(ctx, cache, cacheKey, value, result.TTL)
	return TLSAResult{State: value.State, Records: value.Records}, nil
}

func normalizeDNSHost(host string) string {
	return strings.TrimSuffix(strings.ToLower(strings.TrimSpace(host)), ".")
}

func getTLSARecordsFromDNS(message *dns.Msg, owner string) ([]TLSARecord, bool) {
	records := make([]TLSARecord, 0)
	invalid := false
	for _, answer := range message.Answer {
		tlsa, ok := answer.(*dns.TLSA)
		if !ok || tlsa.Hdr.Class != dns.ClassINET || !strings.EqualFold(tlsa.Hdr.Name, dns.Fqdn(owner)) {
			continue
		}
		if !validTLSAFields(tlsa.Usage, tlsa.Selector, tlsa.MatchingType, tlsa.Certificate) {
			invalid = true
			continue
		}
		records = append(records, TLSARecord{
			CertificateUsage:       tlsa.Usage,
			Selector:               tlsa.Selector,
			MatchingType:           tlsa.MatchingType,
			CertificateAssociation: strings.ToLower(tlsa.Certificate),
		})
	}
	return records, invalid
}

func validTLSAFields(usage, selector, matchingType uint8, associationData string) bool {
	if selector > 1 || matchingType > 2 {
		return false
	}
	decoded, err := hex.DecodeString(associationData)
	if err != nil {
		return false
	}
	switch matchingType {
	case 0:
		return len(decoded) > 0
	case 1:
		return len(decoded) == 32
	case 2:
		return len(decoded) == 64
	default:
		return false
	}
}

func validTLSACacheValue(value TLSACacheValue) bool {
	if value.State != TLSAStateSecureRecords && value.State != TLSAStateSecureAbsent && value.State != TLSAStateSecureUnusable && value.State != TLSAStateInsecure {
		return false
	}
	if value.State == TLSAStateSecureRecords && len(value.Records) == 0 {
		return false
	}
	for _, record := range value.Records {
		if !validTLSAFields(record.CertificateUsage, record.Selector, record.MatchingType, record.CertificateAssociation) {
			return false
		}
	}
	return true
}

func cacheTLSAValue(ctx context.Context, cache *SharedCache, key string, value TLSACacheValue, ttl time.Duration) {
	if ttl <= 0 {
		return
	}
	if ttl > tlsaCacheTTL {
		ttl = tlsaCacheTTL
	}
	_ = cache.Set(ctx, key, value, ttl)
}
