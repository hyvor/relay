package dns

import (
	"errors"
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
		instanceDomain: "relay.hyvor.com",
		ipPtrsForward: map[string]string{
			"smtp1.relay.hyvor.com": "1.1.1.1",
			"smtp2.relay.hyvor.com": "2.2.2.2",
		},
		mxIps: []string{"3.3.3.3", "4.4.4.4"},
	}

	answer, err := getAnswer(dnsServer, "smtp1.relay.hyvor.com.", dns.TypeA)
	assert.NoError(t, err)
	aRecord, ok := answer[0].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "1.1.1.1", aRecord.A.String())

	answer2, err := getAnswer(dnsServer, "smtp1.relay.hyvor.com", dns.TypeA)
	assert.NoError(t, err)
	aRecord2, ok := answer2[0].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "1.1.1.1", aRecord2.A.String())

	answer3, err := getAnswer(dnsServer, "smtp2.relay.hyvor.com", dns.TypeA)
	assert.NoError(t, err)
	aRecord3, ok := answer3[0].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "2.2.2.2", aRecord3.A.String())

	mxAnswer, err := getAnswer(dnsServer, "relay.hyvor.com.", dns.TypeMX)
	assert.NoError(t, err)
	mxRecord, ok := mxAnswer[0].(*dns.MX)
	assert.True(t, ok)
	assert.Equal(t, "mx.relay.hyvor.com.", mxRecord.Mx)
	assert.Equal(t, uint16(10), mxRecord.Preference)

	mxAAnswer, err := getAnswer(dnsServer, "mx.relay.hyvor.com", dns.TypeA)
	assert.NoError(t, err)
	mxARecord1, ok := mxAAnswer[0].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "3.3.3.3", mxARecord1.A.String())
	mxARecord2, ok := mxAAnswer[1].(*dns.A)
	assert.True(t, ok)
	assert.Equal(t, "4.4.4.4", mxARecord2.A.String())

	txtAnswer, err := getAnswer(dnsServer, "relay.hyvor.com.", dns.TypeTXT)
	assert.NoError(t, err)
	txtRecord, ok := txtAnswer[0].(*dns.TXT)
	assert.True(t, ok)
	assert.Equal(t, "v=spf1 ip4:1.1.1.1 ip4:2.2.2.2 -all", txtRecord.Txt[0])
}
