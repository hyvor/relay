package main

import (
	"context"
	"crypto/sha256"
	"crypto/sha512"
	"crypto/x509"
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

type TLSAResult struct {
	State   TLSAState    `json:"state"`
	Records []TLSARecord `json:"records"`
}

var lookupTLSAFunc = lookupTLSA

func lookupTLSA(ctx context.Context, cache *SharedCache, host string) (TLSAResult, error) {
	host = normalizeDNSHost(host)
	if host == "" {
		return TLSAResult{}, fmt.Errorf("%w: empty host", ErrTLSALookup)
	}

	name := "_25._tcp." + host
	cacheKey := dnsCacheKey("tlsa", name)
	var cached TLSAResult
	if found, err := cache.Get(ctx, cacheKey, &cached); err == nil && found {
		if validTLSACacheValue(cached) {
			return cached, nil
		}
		_ = cache.Delete(ctx, cacheKey)
	}

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

	value := TLSAResult{State: TLSAStateInsecure}
	if result.Secure {
		value.State = TLSAStateSecureAbsent
	}
	if result.Message.Rcode == dns.RcodeSuccess {
		var invalid bool
		var complete bool
		value.Records, invalid, complete = getTLSARecordsFromDNS(result.Message, name)
		if !complete {
			return TLSAResult{}, fmt.Errorf("%w: incomplete or looping TLSA alias chain for %s", ErrTLSALookup, name)
		}
		if len(value.Records) > 0 && result.Secure {
			value.State = TLSAStateSecureRecords
		} else if invalid && result.Secure {
			value.State = TLSAStateSecureUnusable
		}
	} else if result.Message.Rcode == dns.RcodeNameError && hasCNAMEAnswer(result.Message) {
		return TLSAResult{}, fmt.Errorf("%w: incomplete TLSA alias response for %s", ErrTLSALookup, name)
	}

	cacheTLSAValue(ctx, cache, cacheKey, value, result.TTL)
	return value, nil
}

func hasCNAMEAnswer(message *dns.Msg) bool {
	for _, answer := range message.Answer {
		if _, ok := answer.(*dns.CNAME); ok {
			return true
		}
	}
	return false
}

func normalizeDNSHost(host string) string {
	return strings.TrimSuffix(strings.ToLower(strings.TrimSpace(host)), ".")
}

func getTLSARecordsFromDNS(message *dns.Msg, owner string) ([]TLSARecord, bool, bool) {
	records := make([]TLSARecord, 0)
	invalid := false
	currentOwner := dns.Fqdn(owner)
	followedAlias := false
	for hops := 0; hops < 8; hops++ {
		foundOwner := false
		for _, answer := range message.Answer {
			tlsa, ok := answer.(*dns.TLSA)
			if !ok || tlsa.Hdr.Class != dns.ClassINET || !strings.EqualFold(tlsa.Hdr.Name, currentOwner) {
				continue
			}
			foundOwner = true
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
		if foundOwner {
			return records, invalid, true
		}
		alias, ok := dnsAliasTarget(message, currentOwner)
		if !ok {
			return records, invalid, !followedAlias || hasSOAInAuthority(message)
		}
		followedAlias = true
		currentOwner = alias
	}
	return records, invalid, false
}

func validTLSAFields(usage, selector, matchingType uint8, associationData string) bool {
	if usage != certificateUsageDANETA && usage != certificateUsageDANEEE {
		return false
	}
	if selector > tlsaSelectorSubjectPublicKey || matchingType > tlsaMatchingTypeSHA512 {
		return false
	}
	decoded, err := hex.DecodeString(associationData)
	if err != nil {
		return false
	}
	switch matchingType {
	case tlsaMatchingTypeExact:
		if selector == tlsaSelectorFullCertificate {
			_, err := x509.ParseCertificate(decoded)
			return err == nil
		}
		_, err := x509.ParsePKIXPublicKey(decoded)
		return err == nil
	case tlsaMatchingTypeSHA256:
		return len(decoded) == sha256.Size
	case tlsaMatchingTypeSHA512:
		return len(decoded) == sha512.Size
	}
	return false
}

func validTLSACacheValue(value TLSAResult) bool {
	if value.State != TLSAStateSecureRecords && value.State != TLSAStateSecureAbsent && value.State != TLSAStateSecureUnusable && value.State != TLSAStateInsecure {
		return false
	}
	for _, record := range value.Records {
		if !validTLSAFields(record.CertificateUsage, record.Selector, record.MatchingType, record.CertificateAssociation) {
			return false
		}
	}
	return true
}

func cacheTLSAValue(ctx context.Context, cache *SharedCache, key string, value TLSAResult, ttl time.Duration) {
	if ttl <= 0 {
		return
	}
	if ttl > tlsaCacheTTL {
		ttl = tlsaCacheTTL
	}
	_ = cache.Set(ctx, key, value, ttl)
}
