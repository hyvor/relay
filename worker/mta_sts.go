package main

import (
	"context"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/miekg/dns"
)

const (
	mtaSTSCacheTTL     = time.Hour
	mtaSTSHTTPTimeout  = 5 * time.Second
	mtaSTSMaxBodyBytes = 64 * 1024
)

var ErrMTASTSLookup = errors.New("MTA-STS lookup failed")

var mtaSTSHTTPClient = &http.Client{
	Timeout: mtaSTSHTTPTimeout,
	CheckRedirect: func(_ *http.Request, _ []*http.Request) error {
		return errors.New("MTA-STS redirects are not allowed")
	},
}

var mtaSTSURL = func(domain string) string {
	return "https://mta-sts." + domain + "/.well-known/mta-sts.txt"
}

type mtaSTSResult struct {
	Enforce    bool
	MXPatterns []string
}

type mtaSTSCacheValue struct {
	Enforce    bool     `json:"enforce"`
	MXPatterns []string `json:"mx_patterns,omitempty"`
}

func (r mtaSTSResult) AllowsMX(host string) bool {
	if !r.Enforce || len(r.MXPatterns) == 0 {
		return true
	}
	for _, pattern := range r.MXPatterns {
		pattern = normalizeDNSHost(pattern)
		if pattern == normalizeDNSHost(host) {
			return true
		}
		if strings.HasPrefix(pattern, "*.") {
			suffix := strings.TrimPrefix(pattern, "*.")
			normalizedHost := normalizeDNSHost(host)
			if strings.HasSuffix(normalizedHost, "."+suffix) && strings.Count(normalizedHost, ".") == strings.Count(suffix, ".")+1 {
				return true
			}
		}
	}
	return false
}

func lookupMTASTS(ctx context.Context, cache *SharedCache, domain string) (mtaSTSResult, error) {
	domain = normalizeDNSHost(domain)
	if domain == "" {
		return mtaSTSResult{}, fmt.Errorf("%w: empty domain", ErrMTASTSLookup)
	}

	cacheKey := dnsCacheKey("mta_sts", domain)
	var cached mtaSTSCacheValue
	if found, err := cache.Get(ctx, cacheKey, &cached); err == nil && found {
		return mtaSTSResult{Enforce: cached.Enforce, MXPatterns: cached.MXPatterns}, nil
	}

	txtResult, err := lookupDNSFunc(ctx, "_mta-sts."+domain, dns.TypeTXT)
	if err != nil {
		return mtaSTSResult{}, fmt.Errorf("%w: TXT lookup: %v", ErrMTASTSLookup, err)
	}
	if txtResult.Message == nil {
		return mtaSTSResult{}, fmt.Errorf("%w: empty TXT response", ErrMTASTSLookup)
	}
	if txtResult.Message.Rcode == dns.RcodeNameError {
		cacheMTASTSValue(ctx, cache, cacheKey, mtaSTSCacheValue{}, txtResult.TTL)
		return mtaSTSResult{}, nil
	}
	if txtResult.Message.Rcode != dns.RcodeSuccess {
		return mtaSTSResult{}, fmt.Errorf("%w: TXT response code %s", ErrMTASTSLookup, dns.RcodeToString[txtResult.Message.Rcode])
	}

	discovery, found := mtaSTSDiscovery(txtResult.Message, domain)
	if !found {
		cacheMTASTSValue(ctx, cache, cacheKey, mtaSTSCacheValue{}, txtResult.TTL)
		return mtaSTSResult{}, nil
	}

	policy, err := fetchMTASTSPolicy(ctx, domain)
	if err != nil {
		return mtaSTSResult{}, err
	}
	if discovery.ID == "" || policy.Version != "STSv1" {
		return mtaSTSResult{}, fmt.Errorf("%w: invalid policy identity for %s", ErrMTASTSLookup, domain)
	}

	value := mtaSTSCacheValue{Enforce: policy.Mode == "enforce", MXPatterns: policy.MXPatterns}
	cacheMTASTSValue(ctx, cache, cacheKey, value, time.Duration(policy.MaxAge)*time.Second)
	return mtaSTSResult{Enforce: value.Enforce, MXPatterns: value.MXPatterns}, nil
}

type mtaSTSDiscoveryValue struct {
	Version string
	ID      string
}

func mtaSTSDiscovery(message *dns.Msg, domain string) (mtaSTSDiscoveryValue, bool) {
	for _, answer := range message.Answer {
		txt, ok := answer.(*dns.TXT)
		if !ok || !strings.EqualFold(txt.Hdr.Name, dns.Fqdn("_mta-sts."+domain)) {
			continue
		}
		fields := parseMTASTSDiscovery(strings.Join(txt.Txt, ""))
		if fields["v"] == "STSv1" && fields["id"] != "" {
			return mtaSTSDiscoveryValue{Version: fields["v"], ID: fields["id"]}, true
		}
	}
	return mtaSTSDiscoveryValue{}, false
}

type mtaSTSPolicy struct {
	Version    string
	Mode       string
	MaxAge     int64
	MXPatterns []string
}

func fetchMTASTSPolicy(ctx context.Context, domain string) (mtaSTSPolicy, error) {
	request, err := http.NewRequestWithContext(ctx, http.MethodGet, mtaSTSURL(domain), nil)
	if err != nil {
		return mtaSTSPolicy{}, fmt.Errorf("%w: create policy request: %v", ErrMTASTSLookup, err)
	}
	response, err := mtaSTSHTTPClient.Do(request)
	if err != nil {
		return mtaSTSPolicy{}, fmt.Errorf("%w: fetch policy: %v", ErrMTASTSLookup, err)
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		return mtaSTSPolicy{}, fmt.Errorf("%w: policy HTTP status %s", ErrMTASTSLookup, response.Status)
	}
	body, err := io.ReadAll(io.LimitReader(response.Body, mtaSTSMaxBodyBytes+1))
	if err != nil || len(body) > mtaSTSMaxBodyBytes {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid policy body", ErrMTASTSLookup)
	}
	fields := parseMTASTSFields(string(body))
	maxAge, err := strconv.ParseInt(fields["max_age"], 10, 64)
	if err != nil || maxAge <= 0 || maxAge > int64((time.Duration(1<<63-1))/time.Second) {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid max_age", ErrMTASTSLookup)
	}
	if fields["version"] != "STSv1" || fields["mode"] == "" || (fields["mode"] != "enforce" && fields["mode"] != "testing" && fields["mode"] != "none") {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid policy fields", ErrMTASTSLookup)
	}
	patterns := []string(nil)
	if fields["mx"] != "" {
		patterns = strings.Split(fields["mx"], "\x00")
	}
	return mtaSTSPolicy{Version: fields["version"], Mode: fields["mode"], MaxAge: maxAge, MXPatterns: patterns}, nil
}

func parseMTASTSFields(policy string) map[string]string {
	fields := make(map[string]string)
	var mxPatterns []string
	for _, line := range strings.Split(policy, "\n") {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		key, value, ok := strings.Cut(line, ":")
		if !ok {
			continue
		}
		key = strings.TrimSpace(key)
		value = strings.TrimSpace(value)
		if key == "mx" {
			mxPatterns = append(mxPatterns, value)
		} else {
			fields[key] = value
		}
	}
	fields["mx"] = strings.Join(mxPatterns, "\x00")
	return fields
}

func parseMTASTSDiscovery(value string) map[string]string {
	fields := make(map[string]string)
	for _, part := range strings.Split(value, ";") {
		key, fieldValue, ok := strings.Cut(part, "=")
		if ok {
			fields[strings.TrimSpace(key)] = strings.TrimSpace(fieldValue)
		}
	}
	return fields
}

func cacheMTASTSValue(ctx context.Context, cache *SharedCache, key string, value mtaSTSCacheValue, ttl time.Duration) {
	if ttl <= 0 {
		return
	}
	if ttl > mtaSTSCacheTTL {
		ttl = mtaSTSCacheTTL
	}
	_ = cache.Set(ctx, key, value, ttl)
}
