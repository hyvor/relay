package main

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"io"
	"log/slog"
	"net"
	"net/http"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
)

func withOutboundDNSResolver(t *testing.T, resolver *DoHResolver) {
	original := outboundDNSResolver
	outboundDNSResolver = resolver
	t.Cleanup(func() {
		outboundDNSResolver = original
	})
}

func TestPingAndReady(t *testing.T) {

	localHttpPort = ":43000"

	context, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(context, serviceState)

	time.Sleep(100 * time.Millisecond)

	resp, err := http.Get("http://localhost" + localHttpPort + "/ping")
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Equal(t, "ok", string(body))

	resp, err = http.Get("http://localhost" + localHttpPort + "/ready")
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, 503, resp.StatusCode)
	body, _ = io.ReadAll(resp.Body)
	assert.Equal(t, "not ready", string(body))

	serviceState.IsSet = true
	resp, err = http.Get("http://localhost" + localHttpPort + "/ready")
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ = io.ReadAll(resp.Body)
	assert.Equal(t, "ready", string(body))

}

// ========== /state ==========

func TestSetState(t *testing.T) {

	localHttpPort = ":43005"

	context, cancel := context.WithCancel(context.Background())
	defer cancel()

	fakeLogger := slog.New(slog.NewTextHandler(io.Discard, nil))
	serviceState := NewServiceState(context, fakeLogger)
	StartHttpServer(context, serviceState)

	time.Sleep(100 * time.Millisecond)

	jsonData := `{"is_set": true}`

	resp, err := http.Post("http://localhost:43005/state", "application/json", strings.NewReader(jsonData))
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "Go state updated")

	assert.True(t, serviceState.IsSet)

}

// ========== /debug/parse-bounce-fbl ==========

func TestDebugParseBounce(t *testing.T) {

	localHttpPort = ":43001"

	context, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(context, serviceState)

	time.Sleep(1200 * time.Millisecond)

	raw := []byte(`To: <bounce@relay.hyvor.com>
Content-Type: multipart/report; report-type=delivery-status;
    boundary="myboundary"

--myboundary

Invalid email

--myboundary
Content-Type: message/delivery-status

Reporting-MTA: dns; google.com

Original-Recipient: rfc822;test@hyvor.com
Final-Recipient: rfc822;test@hyvor.com
Action: failed
Status: 4.0.0

--myboundary
Content-Type: message/rfc822

[original message goes here]

--myboundary--`)
	rawBase64 := base64.StdEncoding.EncodeToString(raw)
	jsonData := `{"raw": "` + rawBase64 + `","type": "bounce"}`

	resp, err := http.Post("http://localhost"+localHttpPort+"/debug/parse-bounce-fbl", "application/json", strings.NewReader(jsonData))
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "Invalid email")
	assert.Contains(t, string(body), "4.0.0")
	assert.Contains(t, string(body), "failed")

}

func TestDebugParseFbl(t *testing.T) {

	localHttpPort = ":43002"

	context, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slogDiscard(),
	}
	StartHttpServer(context, serviceState)

	time.Sleep(100 * time.Millisecond)

	content, err := os.ReadFile("./bounceparse/testdata/arf1.txt")
	assert.NoError(t, err)
	rawBase64 := base64.StdEncoding.EncodeToString(content)
	jsonData := `{"raw": "` + rawBase64 + `","type": "complaint"}`

	resp, err := http.Post("http://localhost"+localHttpPort+"/debug/parse-bounce-fbl", "application/json", strings.NewReader(jsonData))
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "abuse")
	assert.Contains(t, string(body), "SomeGenerator/1.0")
	assert.Contains(t, string(body), "somespammer@example.net")
	assert.Contains(t, string(body), "8787KJKJ3K4J3K4J3K4J3.mail@example.net")
	assert.Contains(t, string(body), "This is an email abuse report for an email message received from IP")

}

// ========== /dns/resolve ==========
//
// These drive the endpoint end-to-end through outboundDNSResolver.LookupAsJson, using the same
// fake wireformat DoH server helpers (dohTestServer/dohResponse) as doh_test.go, rather than
// mocking lookupDNSFunc (that indirection is only used by the outbound-mail codepath in
// mx.go/tlsa.go/mta_sts.go).

func TestDnsResolveARecord(t *testing.T) {

	localHttpPort = ":43006"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(ctx, serviceState)

	time.Sleep(100 * time.Millisecond)

	server := dohTestServer(t, func(writer http.ResponseWriter, query *dns.Msg) {
		assert.Equal(t, "example.com.", query.Question[0].Name)
		assert.Equal(t, dns.TypeA, query.Question[0].Qtype)

		record := &dns.A{
			Hdr: dns.RR_Header{Name: "example.com.", Rrtype: dns.TypeA, Class: dns.ClassINET, Ttl: 300},
			A:   net.ParseIP("1.2.3.4"),
		}
		writer.Header().Set("Content-Type", "application/dns-message")
		_, err := writer.Write(dohResponse(t, query, false, record))
		assert.NoError(t, err)
	})
	withOutboundDNSResolver(t, &DoHResolver{URL: server.URL, Client: server.Client()})

	resp, err := http.Post(
		"http://localhost"+localHttpPort+"/dns/resolve",
		"application/json",
		strings.NewReader(`{"name": "example.com", "type": "A"}`),
	)
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)

	var decoded dnsResolveResponse
	assert.NoError(t, json.NewDecoder(resp.Body).Decode(&decoded))
	assert.Equal(t, 0, decoded.Status)
	assert.Len(t, decoded.Answer, 1)
	assert.Equal(t, "1.2.3.4", decoded.Answer[0].Data)
	assert.Equal(t, uint16(dns.TypeA), decoded.Answer[0].Type)
	assert.Equal(t, uint32(300), decoded.Answer[0].TTL)

}

func TestDnsResolveTxtRecord(t *testing.T) {

	localHttpPort = ":43007"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(ctx, serviceState)

	time.Sleep(100 * time.Millisecond)

	server := dohTestServer(t, func(writer http.ResponseWriter, query *dns.Msg) {
		assert.Equal(t, dns.TypeTXT, query.Question[0].Qtype)

		record := &dns.TXT{
			Hdr: dns.RR_Header{Name: "example.com.", Rrtype: dns.TypeTXT, Class: dns.ClassINET, Ttl: 60},
			Txt: []string{"v=DKIM1; k=rsa"},
		}
		writer.Header().Set("Content-Type", "application/dns-message")
		_, err := writer.Write(dohResponse(t, query, false, record))
		assert.NoError(t, err)
	})
	withOutboundDNSResolver(t, &DoHResolver{URL: server.URL, Client: server.Client()})

	resp, err := http.Post(
		"http://localhost"+localHttpPort+"/dns/resolve",
		"application/json",
		strings.NewReader(`{"name": "example.com", "type": "TXT"}`),
	)
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)

	var decoded dnsResolveResponse
	assert.NoError(t, json.NewDecoder(resp.Body).Decode(&decoded))
	assert.Equal(t, `"v=DKIM1; k=rsa"`, decoded.Answer[0].Data)

}

func TestDnsResolveNxdomain(t *testing.T) {

	localHttpPort = ":43008"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(ctx, serviceState)

	time.Sleep(100 * time.Millisecond)

	server := dohTestServer(t, func(writer http.ResponseWriter, query *dns.Msg) {
		response := new(dns.Msg)
		response.SetReply(query)
		response.Rcode = dns.RcodeNameError
		encoded, err := response.Pack()
		assert.NoError(t, err)
		writer.Header().Set("Content-Type", "application/dns-message")
		_, err = writer.Write(encoded)
		assert.NoError(t, err)
	})
	withOutboundDNSResolver(t, &DoHResolver{URL: server.URL, Client: server.Client()})

	resp, err := http.Post(
		"http://localhost"+localHttpPort+"/dns/resolve",
		"application/json",
		strings.NewReader(`{"name": "nonexistent.example", "type": "A"}`),
	)
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)

	var decoded dnsResolveResponse
	assert.NoError(t, json.NewDecoder(resp.Body).Decode(&decoded))
	assert.Equal(t, dns.RcodeNameError, decoded.Status)
	assert.Empty(t, decoded.Answer)

}

func TestDnsResolveUnsupportedType(t *testing.T) {

	localHttpPort = ":43009"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(ctx, serviceState)

	time.Sleep(100 * time.Millisecond)

	resp, err := http.Post(
		"http://localhost"+localHttpPort+"/dns/resolve",
		"application/json",
		strings.NewReader(`{"name": "example.com", "type": "BOGUS"}`),
	)
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusBadRequest, resp.StatusCode)

}

func TestDnsResolveLookupFailure(t *testing.T) {

	localHttpPort = ":43010"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(ctx, serviceState)

	time.Sleep(100 * time.Millisecond)

	// A resolver whose URL isn't HTTPS fails fast in Lookup without touching the network.
	withOutboundDNSResolver(t, &DoHResolver{URL: "http://example.com", Client: http.DefaultClient})

	resp, err := http.Post(
		"http://localhost"+localHttpPort+"/dns/resolve",
		"application/json",
		strings.NewReader(`{"name": "example.com", "type": "A"}`),
	)
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusBadGateway, resp.StatusCode)

}

func TestDnsResolveInvalidBody(t *testing.T) {

	localHttpPort = ":43011"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(ctx, serviceState)

	time.Sleep(100 * time.Millisecond)

	resp, err := http.Post(
		"http://localhost"+localHttpPort+"/dns/resolve",
		"application/json",
		strings.NewReader(`{"name": ""}`),
	)
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusBadRequest, resp.StatusCode)

}
