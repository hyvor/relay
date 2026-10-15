package main

import (
	"bytes"
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	smtp "github.com/hyvor/relay/worker/smtp"
	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func withMTASTSTXT(t *testing.T, id *string, ttl time.Duration) *int {
	lookups := 0
	withDNSLookupStub(t, func(_ context.Context, name string, recordType uint16) (DNSLookupResult, error) {
		lookups++
		require.Equal(t, dns.TypeTXT, recordType)
		message := &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess}}
		if *id != "" {
			message.Answer = []dns.RR{&dns.TXT{
				Hdr: dns.RR_Header{Name: dns.Fqdn(name), Class: dns.ClassINET, Rrtype: dns.TypeTXT},
				Txt: []string{"v=STSv1; id=" + *id},
			}}
		}
		return DNSLookupResult{Message: message, TTL: ttl}, nil
	})
	return &lookups
}

func withMTASTSPolicyServer(t *testing.T, policy *string) *int {
	fetches := 0
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		fetches++
		if *policy == "" {
			w.WriteHeader(http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "text/plain")
		_, _ = w.Write([]byte(*policy))
	}))
	originalURL := mtaSTSURL
	originalClient := mtaSTSHTTPClient
	mtaSTSURL = func(string) string { return server.URL }
	mtaSTSHTTPClient = server.Client()
	t.Cleanup(func() {
		server.Close()
		mtaSTSURL = originalURL
		mtaSTSHTTPClient = originalClient
	})
	return &fetches
}

const testEnforcePolicy = "version: STSv1\nmode: enforce\nmx: *.example.com\nmax_age: 600\n"

func TestLookupMTASTSEnforcePolicyAndCache(t *testing.T) {
	lookups := 0
	withDNSLookupStub(t, func(_ context.Context, name string, recordType uint16) (DNSLookupResult, error) {
		lookups++
		require.Equal(t, dns.TypeTXT, recordType)
		return DNSLookupResult{
			Message: &dns.Msg{
				MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess},
				Answer: []dns.RR{&dns.TXT{
					Hdr: dns.RR_Header{Name: dns.Fqdn(name), Class: dns.ClassINET, Rrtype: dns.TypeTXT},
					Txt: []string{"v=STSv1; id=policy1"},
				}},
			},
			TTL: time.Minute,
		}, nil
	})

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		assert.Equal(t, "/.well-known/mta-sts.txt", r.URL.Path)
		_, _ = w.Write([]byte("version: STSv1\nmode: enforce\nmx: *.example.com\nmax_age: 600\n"))
	}))
	defer server.Close()
	originalURL := mtaSTSURL
	originalClient := mtaSTSHTTPClient
	mtaSTSURL = func(string) string { return server.URL + "/.well-known/mta-sts.txt" }
	mtaSTSHTTPClient = server.Client()
	t.Cleanup(func() {
		mtaSTSURL = originalURL
		mtaSTSHTTPClient = originalClient
	})

	cache := NewSharedCache(nil)
	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.True(t, result.Enforce)

	result, err = lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.True(t, result.Enforce)
	assert.Equal(t, 1, lookups)
}

func TestLookupMTASTSNonePolicy(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, name string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeTXT, recordType)
		return DNSLookupResult{
			Message: &dns.Msg{
				MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess},
				Answer: []dns.RR{&dns.TXT{
					Hdr: dns.RR_Header{Name: dns.Fqdn(name), Class: dns.ClassINET, Rrtype: dns.TypeTXT},
					Txt: []string{"v=STSv1; id=policy2"},
				}},
			},
			TTL: time.Minute,
		}, nil
	})

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		// The policy is valid, but does not require TLS.
		_, _ = w.Write([]byte("version: STSv1\nmode: none\nmax_age: 600\n"))
	}))
	defer server.Close()
	originalURL := mtaSTSURL
	originalClient := mtaSTSHTTPClient
	mtaSTSURL = func(string) string { return server.URL }
	mtaSTSHTTPClient = server.Client()
	t.Cleanup(func() {
		mtaSTSURL = originalURL
		mtaSTSHTTPClient = originalClient
	})

	cache := NewSharedCache(nil)
	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.False(t, result.Enforce)
}

func TestLookupMTASTSAbsentIsNotAnError(t *testing.T) {
	withDNSLookupStub(t, func(_ context.Context, _ string, recordType uint16) (DNSLookupResult, error) {
		require.Equal(t, dns.TypeTXT, recordType)
		return DNSLookupResult{Message: &dns.Msg{MsgHdr: dns.MsgHdr{Rcode: dns.RcodeSuccess}}, TTL: time.Minute}, nil
	})

	cache := NewSharedCache(nil)
	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.False(t, result.Enforce)
}

func TestMTASTSEnforceRequiresStartTLS(t *testing.T) {
	originalCreateClient := createSmtpClientContext
	var fake fakeConn
	fake.ReadWriter = struct {
		io.Reader
		io.Writer
	}{strings.NewReader("220 mx.example.com ready\r\n250 mx.example.com\r\n"), &bytes.Buffer{}}
	createSmtpClientContext = func(context.Context, string, string) (*smtp.Client, error) {
		return smtp.NewClient(fake, "mx.example.com")
	}
	t.Cleanup(func() { createSmtpClientContext = originalCreateClient })

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	conversation := sendEmailToHostHandlerContextAttempt(
		ctx,
		NewSharedCache(nil),
		&SendRow{Uuid: "test"},
		[]*RecipientRow{{Id: 1, Address: "user@example.com"}},
		"mx.example.com",
		"relay.example.com",
		"127.0.0.1",
		"relay.example.com",
		false,
		true,
		true,
	)
	assert.ErrorIs(t, conversation.NetworkError, ErrTLSRequired)
}

func TestMTASTSOnlyAllowsMatchingMXHosts(t *testing.T) {
	result := mtaSTSResult{Enforce: true, MXPatterns: []string{"*.example.com", "mail.other.test"}}
	assert.True(t, result.AllowsMX("mx.example.com"))
	assert.True(t, result.AllowsMX("mail.other.test"))
	assert.False(t, result.AllowsMX("nested.mx.example.com"))
	assert.False(t, result.AllowsMX("other.test"))
}

func TestLookupMTASTSRefetchesOnlyWhenIDChanges(t *testing.T) {
	id := "policy1"
	policy := testEnforcePolicy
	withMTASTSTXT(t, &id, 0)
	fetches := withMTASTSPolicyServer(t, &policy)
	cache := NewSharedCache(nil)

	_, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	_, err = lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.Equal(t, 1, *fetches)

	id = "policy2"
	policy = "version: STSv1\nmode: testing\nmx: *.example.com\nmax_age: 600\n"
	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.Equal(t, 2, *fetches)
	assert.False(t, result.Enforce)
}

func TestLookupMTASTSKeepsCachedPolicyWhenFetchFails(t *testing.T) {
	id := "policy1"
	policy := testEnforcePolicy
	withMTASTSTXT(t, &id, 0)
	withMTASTSPolicyServer(t, &policy)
	cache := NewSharedCache(nil)

	_, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)

	id = "policy2"
	policy = ""
	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.True(t, result.Enforce)
}

func TestLookupMTASTSKeepsCachedPolicyWhenRecordIsRemoved(t *testing.T) {
	id := "policy1"
	policy := testEnforcePolicy
	withMTASTSTXT(t, &id, 0)
	withMTASTSPolicyServer(t, &policy)
	cache := NewSharedCache(nil)

	_, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)

	id = ""
	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.True(t, result.Enforce)
}

func TestLookupMTASTSCachesNoData(t *testing.T) {
	id := ""
	lookups := withMTASTSTXT(t, &id, time.Minute)
	cache := NewSharedCache(nil)

	_, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	_, err = lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.Equal(t, 1, *lookups)
}

func TestLookupMTASTSCachesFetchFailure(t *testing.T) {
	id := "policy1"
	policy := ""
	withMTASTSTXT(t, &id, 0)
	fetches := withMTASTSPolicyServer(t, &policy)
	cache := NewSharedCache(nil)

	_, err := lookupMTASTS(context.Background(), cache, "example.com")
	assert.ErrorIs(t, err, ErrMTASTSLookup)

	result, err := lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.False(t, result.Enforce)
	assert.Equal(t, 1, *fetches)

	id = "policy2"
	policy = testEnforcePolicy
	result, err = lookupMTASTS(context.Background(), cache, "example.com")
	require.NoError(t, err)
	assert.True(t, result.Enforce)
	assert.Equal(t, 2, *fetches)
}
