package main

import (
	"context"
	"errors"
	"fmt"
	"io"
	"mime"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/miekg/dns"
)

const (
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
	if !r.Enforce {
		return true
	}
	if len(r.MXPatterns) == 0 {
		return false
	}
	normalizedHost := normalizeDNSHost(host)
	for _, pattern := range r.MXPatterns {
		pattern = normalizeDNSHost(pattern)
		if pattern == normalizedHost {
			return true
		}
		if strings.HasPrefix(pattern, "*.") {
			suffix := strings.TrimPrefix(pattern, "*.")
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
		return mtaSTSResult(cached), nil
	}

	queryName := "_mta-sts." + domain
	var txtResult DNSLookupResult
	for redirects := 0; redirects < 8; redirects++ {
		var err error
		txtResult, err = lookupDNSFunc(ctx, queryName, dns.TypeTXT)
		if err != nil {
			return mtaSTSResult{}, fmt.Errorf("%w: TXT lookup: %v", ErrMTASTSLookup, err)
		}
		if txtResult.Message == nil {
			return mtaSTSResult{}, fmt.Errorf("%w: empty TXT response", ErrMTASTSLookup)
		}
		if txtResult.Message.Rcode != dns.RcodeSuccess && txtResult.Message.Rcode != dns.RcodeNameError {
			return mtaSTSResult{}, fmt.Errorf("%w: TXT response code %s", ErrMTASTSLookup, dns.RcodeToString[txtResult.Message.Rcode])
		}
		if _, found := mtaSTSDiscovery(txtResult.Message, queryName); found {
			policy, err := fetchMTASTSPolicy(ctx, domain)
			if err != nil {
				return mtaSTSResult{}, err
			}
			value := mtaSTSCacheValue{Enforce: policy.Mode == "enforce", MXPatterns: policy.MXPatterns}
			cacheMTASTSValue(ctx, cache, cacheKey, value, time.Duration(policy.MaxAge)*time.Second)
			return mtaSTSResult(value), nil
		}
		if target, ok := mtaSTSRedirectTarget(txtResult.Message, queryName); ok {
			queryName = target
			continue
		}
		break
	}
	if txtResult.Message.Rcode == dns.RcodeNameError {
		cacheMTASTSValue(ctx, cache, cacheKey, mtaSTSCacheValue{}, txtResult.TTL)
	}
	return mtaSTSResult{}, nil
}

type mtaSTSDiscoveryValue struct {
	Version string
	ID      string
}

func mtaSTSDiscovery(message *dns.Msg, domain string) (mtaSTSDiscoveryValue, bool) {
	var result mtaSTSDiscoveryValue
	for _, answer := range message.Answer {
		txt, ok := answer.(*dns.TXT)
		if !ok || !strings.EqualFold(txt.Hdr.Name, dns.Fqdn(domain)) {
			continue
		}
		fields := parseMTASTSDiscovery(strings.Join(txt.Txt, ""))
		if fields.Version == "STSv1" && mtaSTSDiscoveryID.MatchString(fields.ID) {
			if result.ID != "" {
				return mtaSTSDiscoveryValue{}, false
			}
			result = fields
		}
	}
	return result, result.ID != ""
}

func mtaSTSRedirectTarget(message *dns.Msg, name string) (string, bool) {
	for _, answer := range message.Answer {
		cname, ok := answer.(*dns.CNAME)
		if ok && strings.EqualFold(cname.Hdr.Name, dns.Fqdn(name)) {
			return strings.TrimSuffix(cname.Target, "."), true
		}
	}
	return "", false
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
	if mediaType, _, err := mime.ParseMediaType(response.Header.Get("Content-Type")); err != nil || (mediaType != "" && mediaType != "text/plain") {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid policy content type", ErrMTASTSLookup)
	}
	body, err := io.ReadAll(io.LimitReader(response.Body, mtaSTSMaxBodyBytes+1))
	if err != nil || len(body) > mtaSTSMaxBodyBytes {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid policy body", ErrMTASTSLookup)
	}
	fields := parseMTASTSFields(string(body))
	maxAge, err := strconv.ParseInt(fields.MaxAge, 10, 64)
	if err != nil || maxAge < 0 || maxAge > 31557600 {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid max_age", ErrMTASTSLookup)
	}
	if fields.Version != "STSv1" || (fields.Mode != "enforce" && fields.Mode != "testing" && fields.Mode != "none") {
		return mtaSTSPolicy{}, fmt.Errorf("%w: invalid policy fields", ErrMTASTSLookup)
	}
	if fields.Mode != "none" && len(fields.MXPatterns) == 0 {
		return mtaSTSPolicy{}, fmt.Errorf("%w: policy has no mx patterns", ErrMTASTSLookup)
	}
	for _, pattern := range fields.MXPatterns {
		if !validMTASTSMXPattern(pattern) {
			return mtaSTSPolicy{}, fmt.Errorf("%w: invalid mx pattern", ErrMTASTSLookup)
		}
	}
	return mtaSTSPolicy{Version: fields.Version, Mode: fields.Mode, MaxAge: maxAge, MXPatterns: fields.MXPatterns}, nil
}

type mtaSTSFields struct {
	Version    string
	Mode       string
	MaxAge     string
	MXPatterns []string
}

func parseMTASTSFields(policy string) mtaSTSFields {
	var fields mtaSTSFields
	seen := make(map[string]bool)
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
			fields.MXPatterns = append(fields.MXPatterns, value)
		} else if !seen[key] {
			seen[key] = true
			switch key {
			case "version":
				fields.Version = value
			case "mode":
				fields.Mode = value
			case "max_age":
				fields.MaxAge = value
			}
		}
	}
	return fields
}

func parseMTASTSDiscovery(value string) mtaSTSDiscoveryValue {
	fields := mtaSTSDiscoveryValue{}
	seen := make(map[string]bool)
	for _, part := range strings.Split(value, ";") {
		key, fieldValue, ok := strings.Cut(part, "=")
		key = strings.TrimSpace(key)
		if ok && !seen[key] {
			seen[key] = true
			switch key {
			case "v":
				fields.Version = strings.TrimSpace(fieldValue)
			case "id":
				fields.ID = strings.TrimSpace(fieldValue)
			}
		}
	}
	return fields
}

var mtaSTSDiscoveryID = regexp.MustCompile(`^[A-Za-z0-9]{1,32}$`)

func validMTASTSMXPattern(pattern string) bool {
	pattern = normalizeDNSHost(pattern)
	if pattern == "" || strings.Contains(pattern[1:], "*") {
		return false
	}
	pattern = strings.TrimPrefix(pattern, "*.")
	for _, label := range strings.Split(pattern, ".") {
		if label == "" || len(label) > 63 || strings.HasPrefix(label, "-") || strings.HasSuffix(label, "-") {
			return false
		}
	}
	return len(pattern) <= 253
}

func cacheMTASTSValue(ctx context.Context, cache *SharedCache, key string, value mtaSTSCacheValue, ttl time.Duration) {
	if ttl <= 0 {
		return
	}
	_ = cache.Set(ctx, key, value, ttl)
}
