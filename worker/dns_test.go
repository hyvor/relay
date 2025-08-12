package main

import (
	"context"
	"errors"
	"log/slog"
	"net"
	"testing"

	"github.com/miekg/dns"
	"github.com/stretchr/testify/assert"
)

type fakeResponseWriter struct {
	msg *dns.Msg
}

func (f *fakeResponseWriter) LocalAddr() net.Addr       { return nil }
func (f *fakeResponseWriter) RemoteAddr() net.Addr      { return nil }
func (f *fakeResponseWriter) WriteMsg(m *dns.Msg) error { f.msg = m; return nil }
func (f *fakeResponseWriter) Write([]byte) (int, error) { return 0, nil }
func (f *fakeResponseWriter) Close() error              { return nil }
func (f *fakeResponseWriter) TsigStatus() error         { return nil }
func (f *fakeResponseWriter) TsigTimersOnly(bool)       {}
func (f *fakeResponseWriter) Hijack()                   {}

func getAnswer(dnsServer DnsServer, query string, dnsType uint16) ([]dns.RR, error) {

	req := new(dns.Msg)
	req.SetQuestion(query, dnsType)

	writer := &fakeResponseWriter{}

	dnsServer.handleRequest(writer, req)

	if writer.msg == nil {
		return nil, errors.New("no response message written")
	}

	if len(writer.msg.Answer) == 0 {
		return nil, errors.New("no answers in response")
	}

	return writer.msg.Answer, nil

}

func TestHandleDNSRequest(t *testing.T) {

	dnsServer := DnsServer{
		ctx:    context.Background(),
		logger: slog.Default(),
		dnsRecords: []GoStateDnsRecord{
			{
				Type:    "A",
				Host:    "smtp1.relay.hyvor.com",
				Content: "1.1.1.1",
				TTL:     3600,
			},
			{
				Type:    "A",
				Host:    "smtp1.relay.hyvor.com",
				Content: "2.2.2.2",
				TTL:     3600,
			},
			{
				Type:    "AAAA",
				Host:    "smtp1.relay.hyvor.com",
				Content: "2001:db8::1",
				TTL:     3600,
			},
			{
				Type:    "CNAME",
				Host:    "blog.relay.hyvor.com",
				Content: "hyvorblogs.io",
				TTL:     300,
			},
			{
				Type:     "MX",
				Host:     "relay.hyvor.com",
				Content:  "mx.relay.hyvor.com",
				TTL:      3600,
				Priority: 10,
			},
			{
				Type:     "MX",
				Host:     "relay.hyvor.com",
				Content:  "mx2.relay.hyvor.com",
				TTL:      3600,
				Priority: 20,
			},
			{
				Type:    "TXT",
				Host:    "relay.hyvor.com",
				Content: "v=spf1 ip4:1.1.1.1 ip4:2.2.2.2 -all",
			},
		},
	}

	answer, err := getAnswer(dnsServer, "smtp1.relay.hyvor.com.", dns.TypeA)
	assert.NoError(t, err)
	assert.Len(t, answer, 2)
	aRecord1, ok := answer[0].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "1.1.1.1", aRecord1.A.String())
	aRecord2, ok := answer[1].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "2.2.2.2", aRecord2.A.String())

	answerAAAA, err := getAnswer(dnsServer, "smtp1.relay.hyvor.com.", dns.TypeAAAA)
	assert.NoError(t, err)
	assert.Len(t, answerAAAA, 1)
	aaaaRecord, ok := answerAAAA[0].(*dns.AAAA)
	assert.True(t, ok)
	assert.Equal(t, "2001:db8::1", aaaaRecord.AAAA.String())

	answerCNAME, err := getAnswer(dnsServer, "blog.relay.hyvor.com.", dns.TypeCNAME)
	assert.NoError(t, err)
	assert.Len(t, answerCNAME, 1)
	cnameRecord, ok := answerCNAME[0].(*dns.CNAME)
	assert.True(t, ok)
	assert.Equal(t, "hyvorblogs.io.", cnameRecord.Target)
	assert.Equal(t, uint32(300), cnameRecord.Hdr.Ttl)

	answerMX, err := getAnswer(dnsServer, "relay.hyvor.com.", dns.TypeMX)
	assert.NoError(t, err)
	assert.Len(t, answerMX, 2)
	mxRecord1, ok := answerMX[0].(*dns.MX)
	assert.True(t, ok)
	assert.Equal(t, "mx.relay.hyvor.com.", mxRecord1.Mx)
	assert.Equal(t, uint16(10), mxRecord1.Preference)
	mxRecord2, ok := answerMX[1].(*dns.MX)
	assert.True(t, ok)
	assert.Equal(t, "mx2.relay.hyvor.com.", mxRecord2.Mx)
	assert.Equal(t, uint16(20), mxRecord2.Preference)

	answerTXT, err := getAnswer(dnsServer, "relay.hyvor.com.", dns.TypeTXT)
	assert.NoError(t, err)
	assert.Len(t, answerTXT, 1)
	txtRecord, ok := answerTXT[0].(*dns.TXT)
	assert.True(t, ok)
	assert.Equal(t, "v=spf1 ip4:1.1.1.1 ip4:2.2.2.2 -all", txtRecord.Txt[0])

	// Test for non-existing record
	_, err = getAnswer(dnsServer, "nonexistent.relay.hyvor.com.", dns.TypeA)
	assert.Error(t, err)

}
