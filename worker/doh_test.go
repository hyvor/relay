package main

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestConfigureOutboundDNSResolverUsesEnvironment(t *testing.T) {
	previous := outboundDNSResolver
	t.Cleanup(func() { outboundDNSResolver = previous })
	t.Setenv("DNS_OVER_HTTPS_URL", "https://resolver.example/dns-query")
	configureOutboundDNSResolver()

	assert.Equal(t, "https://resolver.example/dns-query", outboundDNSResolver.URL)
}

func dohResponse(t *testing.T, request *dns.Msg, authenticated bool, records ...dns.RR) []byte {
	t.Helper()
	response := new(dns.Msg)
	response.SetReply(request)
	response.AuthenticatedData = authenticated
	response.Answer = records
	encoded, err := response.Pack()
	require.NoError(t, err)
	return encoded
}

func TestDoHResolverLookup(t *testing.T) {
	server := httptest.NewTLSServer(http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		assert.Equal(t, "application/dns-message", request.Header.Get("Accept"))
		assert.Equal(t, "application/dns-message", request.Header.Get("Content-Type"))
		body, err := io.ReadAll(request.Body)
		if !assert.NoError(t, err) {
			return
		}
		query := new(dns.Msg)
		if !assert.NoError(t, query.Unpack(body)) {
			return
		}
		if !assert.NotEmpty(t, query.Question) {
			return
		}
		assert.Equal(t, uint16(1), query.Question[0].Qtype)

		record := &dns.A{
			Hdr: dns.RR_Header{Name: "example.com.", Rrtype: dns.TypeA, Class: dns.ClassINET, Ttl: 120},
			A:   []byte{192, 0, 2, 1},
		}
		writer.Header().Set("Content-Type", "application/dns-message")
		writer.Header().Set("Age", "30")
		writer.WriteHeader(http.StatusOK)
		_, err = writer.Write(dohResponse(t, query, true, record))
		assert.NoError(t, err)
	}))
	defer server.Close()

	resolver := &DoHResolver{URL: server.URL, Client: server.Client()}
	result, err := resolver.Lookup(context.Background(), "example.com", dns.TypeA)
	require.NoError(t, err)
	assert.True(t, result.Secure)
	assert.Equal(t, 90*time.Second, result.TTL)
	assert.Len(t, result.Message.Answer, 1)
}

func TestDoHResolverRejectsMismatchedQuestion(t *testing.T) {
	server := httptest.NewTLSServer(http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		body, err := io.ReadAll(request.Body)
		if !assert.NoError(t, err) {
			return
		}
		query := new(dns.Msg)
		if !assert.NoError(t, query.Unpack(body)) {
			return
		}
		if !assert.NotEmpty(t, query.Question) {
			return
		}
		query.Question[0].Name = "other.example."
		writer.Header().Set("Content-Type", "application/dns-message")
		writer.WriteHeader(http.StatusOK)
		_, err = writer.Write(dohResponse(t, query, false))
		assert.NoError(t, err)
	}))
	defer server.Close()

	resolver := &DoHResolver{URL: server.URL, Client: server.Client()}
	_, err := resolver.Lookup(context.Background(), "example.com", dns.TypeA)
	assert.ErrorIs(t, err, ErrDoHLookup)
}

func TestDnsMessageTTLUsesSmallestAnswerOrAuthorityTTL(t *testing.T) {
	message := &dns.Msg{
		Answer: []dns.RR{
			&dns.A{Hdr: dns.RR_Header{Ttl: 300}},
			&dns.A{Hdr: dns.RR_Header{Ttl: 60}},
		},
		Ns: []dns.RR{&dns.SOA{Hdr: dns.RR_Header{Ttl: 120}}},
	}
	assert.Equal(t, time.Minute, dnsMessageTTL(message))
}

func TestDnsMessageTTLUsesSoaMinimumForNegativeAnswers(t *testing.T) {
	message := &dns.Msg{
		Answer: nil,
		Ns:     []dns.RR{&dns.SOA{Hdr: dns.RR_Header{Ttl: 300}, Minttl: 60}},
	}
	assert.Equal(t, time.Minute, dnsMessageTTL(message))
}

func TestDnsMessageTTLRequiresSoaForNegativeAnswers(t *testing.T) {
	message := &dns.Msg{
		MsgHdr: dns.MsgHdr{Rcode: dns.RcodeNameError},
		Answer: []dns.RR{
			&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 300}},
		},
		Ns: []dns.RR{
			&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 120}},
		},
	}
	assert.Zero(t, dnsMessageTTL(message))
}

func TestDnsMessageTTLIgnoresRRSIGWhenClassifyingSignedNegativeAnswer(t *testing.T) {
	message := &dns.Msg{
		Answer: []dns.RR{
			&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 120}},
		},
		Ns: []dns.RR{
			&dns.SOA{Hdr: dns.RR_Header{Ttl: 300}, Minttl: 60},
			&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 10}},
		},
	}
	assert.Equal(t, time.Minute, dnsMessageTTL(message))
}

func TestDnsMessageTTLClassifiesCNAMEWithRRSIGAsNegative(t *testing.T) {
	message := &dns.Msg{
		Answer: []dns.RR{
			&dns.CNAME{Hdr: dns.RR_Header{Ttl: 120}},
			&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 10}},
		},
		Ns: []dns.RR{
			&dns.SOA{Hdr: dns.RR_Header{Ttl: 300}, Minttl: 60},
		},
	}
	assert.Equal(t, time.Minute, dnsMessageTTL(message))
}
