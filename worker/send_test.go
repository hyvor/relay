package main

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"io"
	"net"
	"strings"
	"testing"
	"time"

	smtp "github.com/hyvor/relay/worker/smtp"
	"github.com/stretchr/testify/assert"
)

func TestSendEmail_Accepted(t *testing.T) {

	originalSendEmailToHost := sendEmailToHost
	sendEmailToHost = func(send *SendRow, recipients []*RecipientRow, host, instanceDomain, ip, ptr string) *SmtpConversation {
		return &SmtpConversation{
			NetworkError: nil,
			SmtpError:    nil,
			Steps:        []*SmtpStep{},
		}
	}

	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx.hyvor.com"},
		Expiry: time.Now().Add(1 * time.Hour),
	}

	defer func() {
		sendEmailToHost = originalSendEmailToHost
		delete(mxCache.data, "hyvor.com")
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 1},
		},
		"hyvor.com",
		"relay.com",
		1,
		"1.1.1.1",
		"smtp.relay.com",
	)

	assert.Equal(t, SendResultAccepted, result.RecipientResultCodes[1])
	assert.Equal(t, "mx.hyvor.com", result.RespondedMxHost)

}

func TestSendEmail_500SmtpError(t *testing.T) {

	originalSendEmailToHost := sendEmailToHost
	sendEmailToHost = func(send *SendRow, recipients []*RecipientRow, host, instanceDomain, ip, ptr string) *SmtpConversation {
		return &SmtpConversation{
			NetworkError: nil,
			SmtpError: &SmtpError{
				Code:    511,
				Message: "User does not exist",
			},
			Steps: []*SmtpStep{},
		}
	}

	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx.hyvor.com"},
		Expiry: time.Now().Add(1 * time.Hour),
	}

	defer func() {
		sendEmailToHost = originalSendEmailToHost
		delete(mxCache.data, "hyvor.com")
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 1},
		},
		"hyvor.com",
		"relay.com",
		1,
		"1.1.1.1",
		"smtp.relay.com",
	)

	fmt.Println(result.RecipientResultCodes)

	assert.Equal(t, SendResultBounced, result.RecipientResultCodes[1])
	assert.Equal(t, "mx.hyvor.com", result.RespondedMxHost)

}

func TestSendEmail_4xxSmtpError(t *testing.T) {

	originalSendEmailToHost := sendEmailToHost
	sendEmailToHost = func(send *SendRow, recipients []*RecipientRow, host, instanceDomain, ip, ptr string) *SmtpConversation {
		return &SmtpConversation{
			NetworkError: nil,
			SmtpError: &SmtpError{
				Code:    451,
				Message: "Requested action aborted: local error in processing",
			},
			Steps: []*SmtpStep{},
		}
	}

	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx.hyvor.com"},
		Expiry: time.Now().Add(1 * time.Hour),
	}

	defer func() {
		sendEmailToHost = originalSendEmailToHost
		delete(mxCache.data, "hyvor.com")
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 1},
		},
		"hyvor.com",
		"relay.com",
		1,
		"1.1.1.1",
		"smtp.relay.com",
	)

	assert.Equal(t, SendResultDeferred, result.RecipientResultCodes[1])
	assert.Equal(t, "mx.hyvor.com", result.RespondedMxHost)
	assert.Equal(t, 1, result.NewTryCount)

}

func TestSendEmail_4xxSmtpError_MaxRetries(t *testing.T) {

	originalSendEmailToHost := sendEmailToHost
	sendEmailToHost = func(send *SendRow, recipients []*RecipientRow, host, instanceDomain, ip, ptr string) *SmtpConversation {
		return &SmtpConversation{
			NetworkError: nil,
			SmtpError: &SmtpError{
				Code:    451,
				Message: "Requested action aborted: local error in processing",
			},
			Steps: []*SmtpStep{},
		}
	}

	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx.hyvor.com"},
		Expiry: time.Now().Add(1 * time.Hour),
	}
	defer func() {
		sendEmailToHost = originalSendEmailToHost
		delete(mxCache.data, "hyvor.com")
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 1, TryCount: 6},
		},
		"hyvor.com",
		"relay.com",
		5,
		"1.1.1.1",
		"smtp.relay.com",
	)

	assert.Equal(t, SendResultFailed, result.RecipientResultCodes[1])
	assert.Equal(t, "mx.hyvor.com", result.RespondedMxHost)
	assert.Equal(t, 7, result.NewTryCount)

}

func TestSendEmail_ConnectionError_FirstAttempt(t *testing.T) {

	originalSendEmailToHost := sendEmailToHost
	sendEmailToHost = func(send *SendRow, recipients []*RecipientRow, host, instanceDomain, ip, ptr string) *SmtpConversation {
		return &SmtpConversation{
			NetworkError: context.DeadlineExceeded,
			SmtpError:    nil,
			Steps:        []*SmtpStep{},
		}
	}

	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx.hyvor.com"},
		Expiry: time.Now().Add(1 * time.Hour),
	}
	defer func() {
		sendEmailToHost = originalSendEmailToHost
		delete(mxCache.data, "hyvor.com")
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 1, TryCount: 0},
		},
		"hyvor.com",
		"relay.com",
		0,
		"1.1.1.1",
		"smtp.relay.com",
	)

	assert.Equal(t, SendResultDeferred, result.RecipientResultCodes[1])
	assert.Equal(t, "", result.RespondedMxHost)
	assert.Equal(t, 1, result.NewTryCount)

}

func TestSendEmail_ConnectionError_AfterFirstAttempt(t *testing.T) {

	originalSendEmailToHost := sendEmailToHost
	sendEmailToHost = func(send *SendRow, recipients []*RecipientRow, host, instanceDomain, ip, ptr string) *SmtpConversation {
		return &SmtpConversation{
			NetworkError: context.DeadlineExceeded,
			SmtpError:    nil,
			Steps:        []*SmtpStep{},
		}
	}

	mxCache.data["hyvor.com"] = mxCacheEntry{
		Hosts:  []string{"mx.hyvor.com"},
		Expiry: time.Now().Add(1 * time.Hour),
	}
	defer func() {
		sendEmailToHost = originalSendEmailToHost
		delete(mxCache.data, "hyvor.com")
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 1, TryCount: 1},
		},
		"hyvor.com",
		"relay.com",
		0,
		"1.1.1.1",
		"smtp.relay.com",
	)

	assert.Equal(t, SendResultFailed, result.RecipientResultCodes[1])
	assert.Equal(t, "", result.RespondedMxHost)
	assert.Equal(t, 2, result.NewTryCount)
	assert.Equal(t, context.DeadlineExceeded, result.Error)

}

func TestSendEmail_MxFailed(t *testing.T) {

	originalLookupMxFunc := lookupMxFunc
	originalLookupHostFunc := lookupHostFunc

	customHostError := errors.New("custom host error")

	lookupMxFunc = func(domain string) ([]*net.MX, error) {
		return nil, errors.New("some")
	}
	lookupHostFunc = func(domain string) ([]string, error) {
		return nil, customHostError
	}

	defer func() {
		lookupMxFunc = originalLookupMxFunc
		lookupHostFunc = originalLookupHostFunc
	}()

	result := sendEmailHandler(
		&SendRow{},
		[]*RecipientRow{
			{Id: 4, TryCount: 1},
		},
		"hyvor.com",
		"relay.com",
		0,
		"1.1.1.1",
		"smtp.relay.com",
	)

	assert.Equal(t, SendResultFailed, result.RecipientResultCodes[4])
	assert.Equal(t, "", result.RespondedMxHost)
	assert.Equal(t, 2, result.NewTryCount)
	assert.Equal(t, "MX lookup failed: custom host error", result.Error.Error())

}

func TestSendEmailToHost(t *testing.T) {

	// using our own incoming server for testing
	smtpServerPort = ":25252"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	incomingServer := NewIncomingMailServer(ctx, slogDiscard(), newMetrics())
	go incomingServer.Start("hyvorrelay.io", 2)
	time.Sleep(100 * time.Millisecond)

	send := &SendRow{
		Id:        1,
		Uuid:      "test-uuid",
		From:      "test@hyvor.com",
		RawEmail:  "Subject: Test Email",
		QueueName: "default",
	}

	recipient := &RecipientRow{
		Id:      1,
		Type:    "to",
		Address: "fbl@hyvorrelay.io",
	}

	netResolveTCPAddr = func(network, address string) (*net.TCPAddr, error) {
		return &net.TCPAddr{
			IP:   net.ParseIP("127.0.0.1"),
			Port: 25252,
		}, nil
	}

	convo := sendEmailToHost(
		send,
		[]*RecipientRow{recipient},
		"localhost",
		"relay.com",
		"127.0.0.1",
		"smtp.relay.com",
	)

	assert.NoError(t, convo.NetworkError)
	assert.Nil(t, convo.SmtpError)
	assert.Equal(t, 7, len(convo.Steps))

}

type fakeConn struct {
	io.ReadWriter
}

func (f fakeConn) Close() error                     { return nil }
func (f fakeConn) LocalAddr() net.Addr              { return nil }
func (f fakeConn) RemoteAddr() net.Addr             { return nil }
func (f fakeConn) SetDeadline(time.Time) error      { return nil }
func (f fakeConn) SetReadDeadline(time.Time) error  { return nil }
func (f fakeConn) SetWriteDeadline(time.Time) error { return nil }


func TestSendEmailToHost_OneRecipientFails(t *testing.T) {

	server := `220 somedomain.com at your service
250 Go ahead
250 Sender OK
250 Recipient OK
550 No such user here
354 Body
250 Bye
221 Closing connection
`
	var wrote bytes.Buffer
	var fake fakeConn
	fake.ReadWriter = struct {
		io.Reader
		io.Writer
	}{
		strings.NewReader(server),
		&wrote,
	}

	createSmtpClient = func(host string, _ string) (*smtp.Client, error) {
		return smtp.NewClient(fake, host)
	}

	send := &SendRow{
		Id:        1,
		Uuid:      "test-uuid",
		From:      "test@hyvor.com",
		RawEmail:  "Subject: Test Email",
		QueueName: "default",
	}

	recipient1 := &RecipientRow{
		Id:      1,
		Type:    "to",
		Address: "accept@somedomain.com",
	}

	recipient2 := &RecipientRow{
		Id:      2,
		Type:    "to",
		Address: "fail@somedomain.com",
	}


	convo := sendEmailToHost(
		send,
		[]*RecipientRow{recipient1, recipient2},
		"localhost",
		"relay.com",
		"127.0.0.1",
		"smtp.relay.com",
	)

	assert.NoError(t, convo.NetworkError)
	assert.Nil(t, convo.SmtpError)

	smtpErr, ok := convo.RcptSmtpErrors[2]
	assert.True(t, ok)
	assert.Equal(t, 550, smtpErr.Code)
	assert.Equal(t, "No such user here", smtpErr.Message)

	smtpErr, ok = convo.RcptSmtpErrors[1]
	assert.False(t, ok)
	assert.Nil(t, smtpErr)

}

func TestSendEmailFailedSmtpStatus(t *testing.T) {

	// using our own incoming server for testing
	smtpServerPort = ":25253"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	incomingServer := NewIncomingMailServer(ctx, slogDiscard(), newMetrics())
	go incomingServer.Start("hyvorrelay.io", 2)
	time.Sleep(100 * time.Millisecond)

	send := &SendRow{
		Id:        1,
		Uuid:      "test-uuid",
		From:      "test@hyvor.com",
		RawEmail:  "Subject: Test Email",
		QueueName: "default",
	}

	recipient := &RecipientRow{
		Id:      1,
		Type:    "to",
		Address: "fbl@nothyvorrelya.io", // invalid domain (incoming server won't accept)
	}

	netResolveTCPAddr = func(network, address string) (*net.TCPAddr, error) {
		return &net.TCPAddr{
			IP:   net.ParseIP("127.0.0.1"),
			Port: 25253,
		}, nil
	}

	convo := sendEmailToHost(
		send,
		[]*RecipientRow{recipient},
		"localhost",
		"relay.com",
		"127.0.0.1",
		"smtp.relay.com",
	)

	assert.NoError(t, convo.NetworkError)
	assert.Equal(t, 451, convo.SmtpError.Code)

}

func TestSend_JsonMarsh(t *testing.T) {

	ld := LatencyDuration(150 * time.Millisecond)
	data, err := ld.MarshalJSON()
	assert.NoError(t, err)
	assert.Equal(t, "150", string(data))

	convo := &SmtpConversation{
		NetworkError: errors.New("some error"),
	}
	data, err = convo.MarshalJSON()
	assert.NoError(t, err)
	assert.True(t, strings.Contains(string(data), `"network_error":"some error"`))

}

func TestSendAfterInterval(t *testing.T) {

	// coupling for safety
	intervals := map[int]string{
		1: "15 minutes",
		2: "1 hour",
		3: "2 hours",
		4: "4 hours",
		5: "8 hours",
		6: "16 hours",
	}

	for i := 1; i <= 6; i++ {
		assert.Equal(t, intervals[i], getSendAfterInterval(i))
	}

	assert.Equal(t, "1 day", getSendAfterInterval(10))

}
