package main

import (
	"context"
	"io"
	"log/slog"
	"testing"

	"github.com/joho/godotenv"
	"github.com/stretchr/testify/suite"
)

type IncomingMailSuite struct {
	suite.Suite
}

func (suite *IncomingMailSuite) SetupTest() {
	godotenv.Load()
	err := truncateTestDb()
	suite.NoError(err, "Failed to truncate test database")
}

func TestIncomingMailSuite(t *testing.T) {
	suite.Run(t, new(IncomingMailSuite))
}

func (suite *IncomingMailSuite) TestIncomingMail_HandleBounce() {

	pgpool, err := createNewPgPool(context.Background(), getTestDbConfig(), 1, 1)
	if err != nil {
		suite.T().Fatal("Failed to create pgpool:", err)
	}
	defer pgpool.Close()

	factory, err := NewTestFactory()
	suite.NoError(err)

	factorySend, err := factory.Send(&FactorySend{
		ToAddress: "supun@hyvor.com",
	})
	suite.NoError(err)

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
		RcptTo:         "bounce+" + factorySend.Uuid + "@relay.com",
		InstanceDomain: "relay.com",
	}

	m.Handle(pgpool, slog.New(slog.NewTextHandler(io.Discard, nil)), newMetrics())

	// get debug incoming mail (table debug_incoming_emails)
	var debugType DebugIncomingType = DebugIncomingTypeBounce
	var debugStatus DebugIncomingStatus = DebugIncomingStatusSuccess
	var debugErrorMessage string
	var debugParsedData interface{}
	err = pgpool.QueryRow(context.Background(), `
		SELECT type, status, error_message, parsed_data
		FROM debug_incoming_emails
		LIMIT 1
	`).Scan(&debugType, &debugStatus, &debugErrorMessage, &debugParsedData)

	suite.NoError(err, "Failed to query debug incoming emails")

	suite.Equal(DebugIncomingTypeBounce, debugType)
	suite.Equal(DebugIncomingStatusSuccess, debugStatus)
	suite.Empty(debugErrorMessage)
	suite.NotNil(debugParsedData)

	var suppression struct {
		ProjectId   int
		Email       string
		Description string
	}

	err = pgpool.QueryRow(context.Background(), `
		SELECT project_id, email, description
		FROM suppressions
		LIMIT 1
	`).Scan(&suppression.ProjectId, &suppression.Email, &suppression.Description)
	suite.NoError(err, "Failed to query suppressions")

	suite.Equal(factorySend.ProjectId, suppression.ProjectId)
	suite.Equal("supun@hyvor.com", suppression.Email)
	suite.Equal("Recipient address rejected: User unknown", suppression.Description)

}
