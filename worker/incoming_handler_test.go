package main

import (
	"context"
	"testing"

	"github.com/stretchr/testify/assert"
)

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
	assert.Equal(t, m.Data, bodyMap["raw_email"])

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
	assert.Equal(t, m.Data, bodyMap["raw_email"])

}
