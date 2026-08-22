package main

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"net"
	"strings"
	"time"
)

type MxRecord struct {
	Host     string `json:"host"`
	Priority int    `json:"priority"`
}

type MxCacheValue struct {
	Records []MxRecord `json:"records"`
}

const mxCacheTtl = 1 * time.Hour

var ErrSmtpMxLookupFailed = errors.New("MX lookup failed")

var lookupMxFunc = net.LookupMX
var lookupHostFunc = net.LookupHost

func getMxHostsFromDomain(ctx context.Context, conn *sql.DB, domain string) ([]string, error) {

	cacheKey := "mx:" + domain

	var cached MxCacheValue
	if found, _ := cacheGet(ctx, conn, cacheKey, &cached); found {
		return getHostsFromMxCacheValue(cached), nil
	}

	// Perform the MX lookup
	// Note: mxErr can be set even if there are MX records, so we ignore it and check the length of mx
	mx, _ := lookupMxFunc(domain)

	// if there are MX records, return those hosts
	// net.LookupMX already sorts & verifies the domains
	if len(mx) > 0 {
		value := getMxCacheValueFromMxRecords(mx)
		cacheSet(ctx, conn, cacheKey, value, mxCacheTtl)
		return getHostsFromMxCacheValue(value), nil
	}

	// MX lookup failed or no records found
	// We will check if current domain has any A records
	ips, err := lookupHostFunc(domain)

	if err == nil && len(ips) > 0 {
		value := MxCacheValue{Records: []MxRecord{{Host: domain, Priority: 0}}}
		cacheSet(ctx, conn, cacheKey, value, mxCacheTtl)
		return getHostsFromMxCacheValue(value), nil
	}

	return nil, fmt.Errorf("%w: %s", ErrSmtpMxLookupFailed, err)
}

func getMxCacheValueFromMxRecords(mxRecords []*net.MX) MxCacheValue {
	records := make([]MxRecord, 0, len(mxRecords))
	for _, mxRecord := range mxRecords {
		host := strings.TrimSuffix(mxRecord.Host, ".")
		records = append(records, MxRecord{Host: host, Priority: int(mxRecord.Pref)})
	}
	return MxCacheValue{Records: records}
}

func getHostsFromMxCacheValue(value MxCacheValue) []string {
	hosts := make([]string, 0, len(value.Records))
	for _, record := range value.Records {
		hosts = append(hosts, record.Host)
	}
	return hosts
}
