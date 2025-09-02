package main

import (
	"context"
	"net"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestSendEmailToHost(t *testing.T) {

	// using our own incoming server for testing
	smtpServerPort = ":25252"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	incomingServer := NewIncomingMailServer(ctx, slogDiscard(), newMetrics())
	go incomingServer.Start("hyvorrelay.io", 2)
	time.Sleep(10 * time.Millisecond)

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

	assert.NoError(t, convo.Error)
	assert.Equal(t, 0, convo.SmtpErrorStatus)
	assert.Equal(t, 7, len(convo.Steps))

}

func TestSendEmailFailedSmtpStatus(t *testing.T) {

	// using our own incoming server for testing
	smtpServerPort = ":25253"

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	incomingServer := NewIncomingMailServer(ctx, slogDiscard(), newMetrics())
	go incomingServer.Start("hyvorrelay.io", 2)
	time.Sleep(10 * time.Millisecond)

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

	assert.Equal(t, 451, convo.SmtpErrorStatus)

}
