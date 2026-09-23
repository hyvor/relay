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

func dohTestServer(t *testing.T, respond func(writer http.ResponseWriter, query *dns.Msg)) *httptest.Server {
	t.Helper()
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

		respond(writer, query)
	}))
	t.Cleanup(server.Close)
	return server
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
	server := dohTestServer(t, func(writer http.ResponseWriter, query *dns.Msg) {
		assert.Equal(t, dns.TypeA, query.Question[0].Qtype)

		record := &dns.A{
			Hdr: dns.RR_Header{Name: "example.com.", Rrtype: dns.TypeA, Class: dns.ClassINET, Ttl: 120},
			A:   []byte{192, 0, 2, 1},
		}
		writer.Header().Set("Content-Type", "application/dns-message")
		writer.Header().Set("Age", "30")
		_, err := writer.Write(dohResponse(t, query, true, record))
		assert.NoError(t, err)
	})

	resolver := &DoHResolver{URL: server.URL, Client: server.Client()}
	result, err := resolver.Lookup(context.Background(), "example.com", dns.TypeA)
	require.NoError(t, err)
	assert.True(t, result.Secure)
	// The 120s record TTL less the 30s the resolver reported the answer as aged.
	assert.Equal(t, 90*time.Second, result.TTL)
	assert.Len(t, result.Message.Answer, 1)
}

func TestDoHResolverRejectsMismatchedQuestion(t *testing.T) {
	server := dohTestServer(t, func(writer http.ResponseWriter, query *dns.Msg) {
		query.Question[0].Name = "other.example."
		writer.Header().Set("Content-Type", "application/dns-message")
		_, err := writer.Write(dohResponse(t, query, false))
		assert.NoError(t, err)
	})

	resolver := &DoHResolver{URL: server.URL, Client: server.Client()}
	_, err := resolver.Lookup(context.Background(), "example.com", dns.TypeA)
	assert.ErrorIs(t, err, ErrDoHLookup)
}

func TestDoHResolverLookupAsJson(t *testing.T) {
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

	resolver := &DoHResolver{URL: server.URL, Client: server.Client()}
	response, err := resolver.LookupAsJson(context.Background(), "example.com", dns.TypeTXT)
	require.NoError(t, err)
	assert.Equal(t, 0, response.Status)
	require.Len(t, response.Answer, 1)
	assert.Equal(t, "example.com.", response.Answer[0].Name)
	assert.Equal(t, uint16(dns.TypeTXT), response.Answer[0].Type)
	assert.Equal(t, uint32(60), response.Answer[0].TTL)
	assert.Equal(t, `"v=DKIM1; k=rsa"`, response.Answer[0].Data)
}

func TestDoHResolverLookupAsJsonPropagatesError(t *testing.T) {
	resolver := &DoHResolver{URL: "http://example.com", Client: http.DefaultClient}
	_, err := resolver.LookupAsJson(context.Background(), "example.com", dns.TypeA)
	assert.ErrorIs(t, err, ErrDoHLookup)
}

func TestDnsMessageTTL(t *testing.T) {
	tests := []struct {
		name    string
		message *dns.Msg
		want    time.Duration
	}{
		{
			name: "smallest of answer and authority",
			message: &dns.Msg{
				Answer: []dns.RR{
					&dns.A{Hdr: dns.RR_Header{Ttl: 300}},
					&dns.A{Hdr: dns.RR_Header{Ttl: 60}},
				},
				Ns: []dns.RR{&dns.SOA{Hdr: dns.RR_Header{Ttl: 120}}},
			},
			want: time.Minute,
		},
		{
			name: "empty answer uses the SOA minimum",
			message: &dns.Msg{
				Ns: []dns.RR{&dns.SOA{Hdr: dns.RR_Header{Ttl: 300}, Minttl: 60}},
			},
			want: time.Minute,
		},
		{
			name: "negative answer without a SOA has no TTL",
			message: &dns.Msg{
				MsgHdr: dns.MsgHdr{Rcode: dns.RcodeNameError},
				Ns:     []dns.RR{&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 120}}},
			},
			want: 0,
		},
		{
			// The RRSIG in the authority section must not win the minimum.
			name: "answer of only RRSIG is negative",
			message: &dns.Msg{
				Answer: []dns.RR{&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 120}}},
				Ns: []dns.RR{
					&dns.SOA{Hdr: dns.RR_Header{Ttl: 300}, Minttl: 60},
					&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 10}},
				},
			},
			want: time.Minute,
		},
		{
			name: "answer of CNAME with RRSIG is negative",
			message: &dns.Msg{
				Answer: []dns.RR{
					&dns.CNAME{Hdr: dns.RR_Header{Ttl: 120}},
					&dns.RRSIG{Hdr: dns.RR_Header{Ttl: 10}},
				},
				Ns: []dns.RR{&dns.SOA{Hdr: dns.RR_Header{Ttl: 300}, Minttl: 60}},
			},
			want: time.Minute,
		},
	}

	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			assert.Equal(t, test.want, dnsMessageTTL(test.message))
		})
	}
}
