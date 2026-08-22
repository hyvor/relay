package main

import (
	"context"
	"database/sql"
	"fmt"
	"io"
	"net"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// MTA-STS (RFC 8461): a domain opts in by publishing a `_mta-sts.<domain>`
// TXT record and serving a policy file at
// https://mta-sts.<domain>/.well-known/mta-sts.txt. We only care about
// whether that policy says "mode: enforce" - "testing" and "none" (or no
// policy at all) are treated the same as no policy, since only "enforce"
// requires us to make STARTTLS mandatory.

type MtaStsCacheValue struct {
	Enforced bool `json:"enforced"`
}

// mtaStsDefaultTtl is used whenever there's no policy (or an unusable one)
// to read a max_age from: no TXT record, fetch failure, or a malformed
// policy body. The task doc only specifies the TTL for the positive case
// (max_age from the policy); this is a reasonable default for the negative
// case, matching the TTL used for MX/DANE caching.
const mtaStsDefaultTtl = 1 * time.Hour

// mtaStsMinTtl guards against a policy publishing an unreasonably small
// (or malformed) max_age, which would otherwise cause a fetch on every send.
const mtaStsMinTtl = 5 * time.Minute

var lookupTxtFunc = net.LookupTXT
var fetchMtaStsPolicyFunc = fetchMtaStsPolicyOverHttp

func getMtaStsEnforced(ctx context.Context, conn *sql.DB, domain string) (bool, error) {

	cacheKey := "mta_sts:" + domain

	var cached MtaStsCacheValue
	if found, _ := cacheGet(ctx, conn, cacheKey, &cached); found {
		return cached.Enforced, nil
	}

	enforced, ttl := fetchMtaStsPolicy(ctx, domain)

	cacheSet(ctx, conn, cacheKey, MtaStsCacheValue{Enforced: enforced}, ttl)

	return enforced, nil

}

func fetchMtaStsPolicy(ctx context.Context, domain string) (enforced bool, ttl time.Duration) {

	if !hasMtaStsTxtRecord(domain) {
		return false, mtaStsDefaultTtl
	}

	body, err := fetchMtaStsPolicyFunc(ctx, domain)
	if err != nil {
		return false, mtaStsDefaultTtl
	}

	mode, maxAge := parseMtaStsPolicy(body)

	ttl = time.Duration(maxAge) * time.Second
	if ttl < mtaStsMinTtl {
		ttl = mtaStsDefaultTtl
	}

	return mode == "enforce", ttl

}

func hasMtaStsTxtRecord(domain string) bool {
	records, err := lookupTxtFunc("_mta-sts." + domain)
	if err != nil {
		return false
	}
	for _, record := range records {
		if strings.HasPrefix(record, "v=STSv1") {
			return true
		}
	}
	return false
}

func fetchMtaStsPolicyOverHttp(ctx context.Context, domain string) (string, error) {

	url := "https://mta-sts." + domain + "/.well-known/mta-sts.txt"

	req, err := http.NewRequestWithContext(ctx, "GET", url, nil)
	if err != nil {
		return "", err
	}

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("unexpected status code fetching MTA-STS policy for %s: %d", domain, resp.StatusCode)
	}

	body, err := io.ReadAll(io.LimitReader(resp.Body, 64*1024))
	if err != nil {
		return "", err
	}

	return string(body), nil

}

// parseMtaStsPolicy parses the simple `key: value` line format used by
// MTA-STS policy files, returning the "mode" and "max_age" fields.
func parseMtaStsPolicy(body string) (mode string, maxAge int) {
	for _, line := range strings.Split(body, "\n") {
		key, value, found := strings.Cut(line, ":")
		if !found {
			continue
		}

		key = strings.TrimSpace(key)
		value = strings.TrimSpace(value)

		switch key {
		case "mode":
			mode = value
		case "max_age":
			if n, err := strconv.Atoi(value); err == nil {
				maxAge = n
			}
		}
	}
	return mode, maxAge
}
