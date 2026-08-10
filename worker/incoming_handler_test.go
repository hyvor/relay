package main

import (
	"context"
	"testing"

	"github.com/hyvor/relay/worker/bounceparse"
	"github.com/stretchr/testify/assert"
)

func TestBuildDsnPayload_BounceReason(t *testing.T) {
	dsn := &bounceparse.Dsn{
		ReadableText: "some text",
		Recipients: []bounceparse.DsnRecipient{
			{EmailAddress: "a@example.com", Status: bounceparse.DsnStatus{5, 1, 1}, Action: "failed"},
			{EmailAddress: "b@example.com", Status: bounceparse.DsnStatus{5, 7, 1}, Action: "failed"},
			{EmailAddress: "c@example.com", Status: bounceparse.DsnStatus{5, 3, 0}, Action: "failed"},
		},
	}

	payload := buildDsnPayload(dsn)

	assert.Equal(t, "some text", payload.ReadableText)
	assert.Len(t, payload.Recipients, 3)
	assert.Equal(t, string(BounceReasonRecipient), payload.Recipients[0].BounceReason)
	assert.Equal(t, string(BounceReasonInfrastructure), payload.Recipients[1].BounceReason)
	assert.Equal(t, string(BounceReasonUnknown), payload.Recipients[2].BounceReason)
}

func TestIncomingMail_ReturnsWhenApiKey(t *testing.T) {

	m := &IncomingMail{
		Data: []byte(`From: sender@example.com
To: recipient@example.com
Subject: Test Email

This is a test email message.
`),
		MailFrom:       "doesnotmatter@example.com",
		RcptTo:         "doesnotmatter@example.com",
		InstanceDomain: "doesnotmatter.com",
		ApiKey:         "test-api-key",
	}

	m.Handle(
		context.Background(),
		slogDiscard(),
		newMetrics(),
	)

}

func TestIncomingMail_HandleBounce(t *testing.T) {

	m := &IncomingMail{
		Data: []byte(`From: MAILER-DAEMON@example.com
To: sender@example.org
Subject: Delivery Status Notification (Failure)
MIME-Version: 1.0
Content-Type: multipart/report; report-type=delivery-status;
    boundary="dsn-boundary"

--dsn-boundary
Content-Type: text/plain; charset=UTF-8

Recipient address rejected: User unknown
--dsn-boundary
Content-Type: message/delivery-status

Reporting-MTA: dns; mail.example.com
Arrival-Date: Tue, 30 Jul 2025 14:59:00 +0000

Final-Recipient: rfc822; recipient@example.net
Action: failed
Status: 5.1.1
Diagnostic-Code: smtp; 550 5.1.1 User unknown
Last-Attempt-Date: Tue, 30 Jul 2025 15:00:00 +0000

--dsn-boundary
Content-Type: message/rfc822

Return-Path: <sender@example.org>
From: sender@example.org
To: recipient@example.net
Subject: Test Email
Date: Tue, 30 Jul 2025 14:58:00 +0000
Message-ID: <original12345@example.org>
Content-Type: text/plain; charset=UTF-8

This is a test email message.

--dsn-boundary--
`),
		MailFrom:       "sender@example.org",
		RcptTo:         "bounce+uuid@relay.com",
		InstanceDomain: "relay.com",
	}

	var calledMethod string
	var calledEndpoint string
	var calledBody interface{}

	CallLocalApi = func(
		ctx context.Context,
		method,
		endpoint string,
		body,
		responseJsonObject interface{},
	) error {
		calledMethod = method
		calledEndpoint = endpoint
		calledBody = body
		return nil
	}

	m.Handle(
		context.Background(),
		slogDiscard(),
		newMetrics(),
	)

	assert.Equal(t, "POST", calledMethod)
	assert.Equal(t, "/incoming", calledEndpoint)

	bodyMap, ok := calledBody.(map[string]interface{})
	assert.True(t, ok)

	assert.Equal(t, IncomingMailTypeBounce, bodyMap["type"])
	assert.Equal(t, "uuid", bodyMap["bounce_uuid"])
	assert.Contains(t, bodyMap, "dsn")
	assert.Equal(t, "sender@example.org", bodyMap["mail_from"])
	assert.Equal(t, "bounce+uuid@relay.com", bodyMap["rcpt_to"])
	assert.Equal(t, string(m.Data), bodyMap["raw_email"])

	dsn, ok := bodyMap["dsn"].(dsnPayload)
	assert.True(t, ok)
	assert.Len(t, dsn.Recipients, 1)
	assert.Equal(t, "recipient@example.net", dsn.Recipients[0].EmailAddress)
	assert.Equal(t, "5.1.1", dsn.Recipients[0].Status)
	assert.Equal(t, "failed", dsn.Recipients[0].Action)
	assert.Equal(t, string(BounceReasonRecipient), dsn.Recipients[0].BounceReason)

}

func TestIncomingMail_HandleFbl(t *testing.T) {

	m := &IncomingMail{
		Data: []byte(`MIME-Version: 1.0
Content-Type: multipart/report; report-type=feedback-report;
     boundary="myinnocentboundary"

--myinnocentboundary
Content-Type: text/plain; charset="US-ASCII"
Content-Transfer-Encoding: 7bit

This is an email abuse report for an email message.

--myinnocentboundary
Content-Type: message/feedback-report

Feedback-Type: abuse

--myinnocentboundary
Content-Type: message/rfc822

Return-Path: <return@hyvor.com>

--myinnocentboundary--
`),
		MailFrom:       "sender@example.org",
		RcptTo:         "fbl@relay.com",
		InstanceDomain: "relay.com",
	}

	var calledMethod string
	var calledEndpoint string
	var calledBody interface{}

	CallLocalApi = func(
		ctx context.Context,
		method,
		endpoint string,
		body,
		responseJsonObject interface{},
	) error {
		calledMethod = method
		calledEndpoint = endpoint
		calledBody = body
		return nil
	}

	m.Handle(
		context.Background(),
		slogDiscard(),
		newMetrics(),
	)

	assert.Equal(t, "POST", calledMethod)
	assert.Equal(t, "/incoming", calledEndpoint)

	bodyMap, ok := calledBody.(map[string]interface{})
	assert.True(t, ok)

	assert.Equal(t, IncomingMailTypeFbl, bodyMap["type"])
	assert.Contains(t, bodyMap, "arf")
	assert.Equal(t, "sender@example.org", bodyMap["mail_from"])
	assert.Equal(t, "fbl@relay.com", bodyMap["rcpt_to"])
	assert.Equal(t, string(m.Data), bodyMap["raw_email"])

}
