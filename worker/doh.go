package main

import (
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"io"
	"mime"
	"net/http"
	"net/url"
	"os"
	"strconv"
	"strings"
	"time"

	"github.com/miekg/dns"
)

const (
	dohRequestTimeout   = 5 * time.Second
	dohMaxDnsMessageLen = 65535
)

var ErrDoHLookup = errors.New("DNS lookup failed")

type DoHResolver struct {
	URL    string
	Client *http.Client
}

type DNSLookupResult struct {
	Message *dns.Msg
	TTL     time.Duration
	Secure  bool
}

func NewDoHResolver() *DoHResolver {
	endpoint := os.Getenv("DNS_OVER_HTTPS_URL")
	if endpoint == "" {
		panic("DNS_OVER_HTTPS_URL is not set")
	}

	return &DoHResolver{
		URL: endpoint,
		Client: &http.Client{
			Timeout: dohRequestTimeout,
			CheckRedirect: func(_ *http.Request, _ []*http.Request) error {
				return errors.New("DoH redirects are not allowed")
			},
		},
	}
}

var outboundDNSResolver *DoHResolver

func configureOutboundDNSResolver() {
	outboundDNSResolver = NewDoHResolver()
}

func dnsCacheKey(kind, name string) string {
	var endpoint string
	if outboundDNSResolver != nil {
		endpoint = outboundDNSResolver.URL
	}

	hash := sha256.Sum256([]byte(endpoint))
	return "dns:v1:" + hex.EncodeToString(hash[:8]) + ":" + kind + ":" + name
}

func (r *DoHResolver) Lookup(ctx context.Context, name string, recordType uint16) (DNSLookupResult, error) {
	if r == nil || r.Client == nil || r.URL == "" {
		return DNSLookupResult{}, fmt.Errorf("%w: resolver is not configured", ErrDoHLookup)
	}
	endpoint, err := url.Parse(r.URL)
	if err != nil || endpoint.Scheme != "https" || endpoint.Host == "" {
		return DNSLookupResult{}, fmt.Errorf("%w: endpoint must use HTTPS", ErrDoHLookup)
	}

	message := new(dns.Msg)
	message.SetQuestion(dns.Fqdn(name), recordType)
	message.RecursionDesired = true
	message.SetEdns0(1232, true)
	message.Id = 0

	body, err := message.Pack()
	if err != nil {
		return DNSLookupResult{}, fmt.Errorf("%w: encode query: %v", ErrDoHLookup, err)
	}

	requestCtx, cancel := context.WithTimeout(ctx, dohRequestTimeout)
	defer cancel()
	request, err := http.NewRequestWithContext(requestCtx, http.MethodPost, r.URL, bytes.NewReader(body))
	if err != nil {
		return DNSLookupResult{}, fmt.Errorf("%w: create request: %v", ErrDoHLookup, err)
	}
	request.Header.Set("Accept", "application/dns-message")
	request.Header.Set("Content-Type", "application/dns-message")

	response, err := r.Client.Do(request)
	if err != nil {
		return DNSLookupResult{}, fmt.Errorf("%w: request: %v", ErrDoHLookup, err)
	}
	defer response.Body.Close()
	if response.StatusCode < http.StatusOK || response.StatusCode >= http.StatusMultipleChoices {
		return DNSLookupResult{}, fmt.Errorf("%w: HTTP status %s", ErrDoHLookup, response.Status)
	}
	contentType, _, contentTypeErr := mime.ParseMediaType(response.Header.Get("Content-Type"))
	if contentTypeErr != nil || strings.ToLower(contentType) != "application/dns-message" {
		return DNSLookupResult{}, fmt.Errorf("%w: unsupported content type %q", ErrDoHLookup, contentType)
	}

	responseBody, err := io.ReadAll(io.LimitReader(response.Body, dohMaxDnsMessageLen+1))
	if err != nil {
		return DNSLookupResult{}, fmt.Errorf("%w: read response: %v", ErrDoHLookup, err)
	}
	if len(responseBody) > dohMaxDnsMessageLen {
		return DNSLookupResult{}, fmt.Errorf("%w: response is too large", ErrDoHLookup)
	}

	result := new(dns.Msg)
	if err := result.Unpack(responseBody); err != nil {
		return DNSLookupResult{}, fmt.Errorf("%w: decode response: %v", ErrDoHLookup, err)
	}
	if !result.Response || result.Truncated || result.Opcode != dns.OpcodeQuery || len(result.Question) != 1 ||
		!strings.EqualFold(result.Question[0].Name, dns.Fqdn(name)) ||
		result.Question[0].Qtype != recordType || result.Question[0].Qclass != dns.ClassINET {
		return DNSLookupResult{}, fmt.Errorf("%w: response question does not match request", ErrDoHLookup)
	}

	ttl := dnsMessageTTL(result)
	if age, parseErr := strconv.ParseUint(response.Header.Get("Age"), 10, 32); parseErr == nil {
		ageDuration := time.Duration(age) * time.Second
		if ageDuration >= ttl {
			ttl = 0
		} else {
			ttl -= ageDuration
		}
	}

	return DNSLookupResult{
		Message: result,
		TTL:     ttl,
		Secure:  result.AuthenticatedData,
	}, nil
}

// dnsAnswerResponse and dnsResolveResponse mirror the classic Cloudflare/Google DoH JSON
// shape, since that's what the PHP backend's ResolveResult::fromArray() already parses.
type dnsAnswerResponse struct {
	Name string `json:"name"`
	Type uint16 `json:"type"`
	TTL  uint32 `json:"TTL"`
	Data string `json:"data"`
}

type dnsResolveResponse struct {
	Status int                 `json:"Status"`
	Answer []dnsAnswerResponse `json:"Answer,omitempty"`
}

// returns Google/Cloudflare JSON-formatted DNS response.
func (r *DoHResolver) LookupAsJson(ctx context.Context, name string, recordType uint16) (dnsResolveResponse, error) {
	result, err := r.Lookup(ctx, name, recordType)
	if err != nil {
		return dnsResolveResponse{}, err
	}

	return dnsMessageToResponse(result.Message), nil
}

func dnsMessageToResponse(message *dns.Msg) dnsResolveResponse {
	answers := make([]dnsAnswerResponse, 0, len(message.Answer))

	for _, rr := range message.Answer {
		header := rr.Header()
		answers = append(answers, dnsAnswerResponse{
			Name: header.Name,
			Type: header.Rrtype,
			TTL:  header.Ttl,
			Data: dnsRDataString(rr),
		})
	}

	return dnsResolveResponse{
		Status: message.Rcode,
		Answer: answers,
	}
}

func dnsRDataString(rr dns.RR) string {
	switch record := rr.(type) {
	case *dns.A:
		return record.A.String()
	case *dns.AAAA:
		return record.AAAA.String()
	case *dns.MX:
		return fmt.Sprintf("%d %s", record.Preference, record.Mx)
	case *dns.TXT:
		chunks := make([]string, len(record.Txt))
		for i, chunk := range record.Txt {
			chunks[i] = quoteTxtChunk(chunk)
		}
		return strings.Join(chunks, " ")
	case *dns.PTR:
		return record.Ptr
	case *dns.CNAME:
		return record.Target
	case *dns.NS:
		return record.Ns
	default:
		return rr.String()
	}
}

func quoteTxtChunk(chunk string) string {
	var b strings.Builder
	b.WriteByte('"')
	for i := 0; i < len(chunk); i++ {
		c := chunk[i]
		if c == '\\' || c == '"' {
			b.WriteByte('\\')
		}
		b.WriteByte(c)
	}
	b.WriteByte('"')
	return b.String()
}

func dnsMessageTTL(message *dns.Msg) time.Duration {
	negative := message.Rcode != dns.RcodeSuccess
	if message.Rcode == dns.RcodeSuccess {
		negative = true
		for _, record := range message.Answer {
			switch record.(type) {
			case *dns.CNAME, *dns.RRSIG:
			default:
				negative = false
			}
		}
	}
	if negative {
		var ttl uint32
		set := false
		for _, record := range message.Ns {
			soa, ok := record.(*dns.SOA)
			if !ok {
				continue
			}
			current := soa.Hdr.Ttl
			if soa.Minttl < current {
				current = soa.Minttl
			}
			if !set || current < ttl {
				ttl = current
				set = true
			}
		}
		if !set {
			return 0
		}
		return time.Duration(ttl) * time.Second
	}

	var ttl uint32
	set := false
	for _, record := range append(append([]dns.RR{}, message.Answer...), message.Ns...) {
		current := record.Header().Ttl
		if !set || current < ttl {
			ttl = current
			set = true
		}
	}
	if !set {
		return 0
	}
	return time.Duration(ttl) * time.Second
}
