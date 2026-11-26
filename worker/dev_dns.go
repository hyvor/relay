package main

import (
	"net"
	"os"
	"strings"
	"time"

	"github.com/miekg/dns"
)

// Dev-only DNS overrides, e.g. DEV_DNS_OVERRIDES="simulator.net=10.10.0.2".
func parseDevDNSOverrides(raw string) map[string]net.IP {
	overrides := map[string]net.IP{}
	for _, entry := range strings.Split(raw, ",") {
		name, ip, ok := strings.Cut(strings.TrimSpace(entry), "=")
		parsed := net.ParseIP(strings.TrimSpace(ip))
		if !ok || parsed == nil {
			continue
		}
		overrides[strings.TrimSuffix(strings.ToLower(strings.TrimSpace(name)), ".")] = parsed
	}
	return overrides
}

func devDNSOverride(overrides map[string]net.IP, name string, recordType uint16) (DNSLookupResult, bool) {
	name = strings.TrimSuffix(strings.ToLower(name), ".")
	ip, ok := overrides[name]
	if !ok {
		return DNSLookupResult{}, false
	}

	message := new(dns.Msg)
	message.SetQuestion(dns.Fqdn(name), recordType)
	message.Response = true
	message.Rcode = dns.RcodeSuccess

	header := dns.RR_Header{Name: dns.Fqdn(name), Class: dns.ClassINET, Ttl: 60}
	if ip4 := ip.To4(); ip4 != nil && recordType == dns.TypeA {
		header.Rrtype = dns.TypeA
		message.Answer = append(message.Answer, &dns.A{Hdr: header, A: ip4})
	} else if ip4 == nil && recordType == dns.TypeAAAA {
		header.Rrtype = dns.TypeAAAA
		message.Answer = append(message.Answer, &dns.AAAA{Hdr: header, AAAA: ip})
	}

	return DNSLookupResult{Message: message, TTL: 0 * time.Second}, true
}

var devDNSOverrides = parseDevDNSOverrides(os.Getenv("DEV_DNS_OVERRIDES"))
