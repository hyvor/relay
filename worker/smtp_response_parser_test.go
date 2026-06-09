package main

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestSmtpResponseParser_IsBounce(t *testing.T) {
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 1, 1}, "User unknown").IsBounce())
	assert.True(t, NewSmtpResponseParser(0, [3]int{5, 1, 1}, "User unknown").IsBounce())
	assert.False(t, NewSmtpResponseParser(250, [3]int{2, 0, 0}, "OK").IsBounce())
	assert.False(t, NewSmtpResponseParser(450, [3]int{4, 2, 1}, "Try later").IsBounce())
	assert.False(t, NewSmtpResponseParser(0, [3]int{0, 0, 0}, "network error").IsBounce())
}

func TestSmtpResponseParser_IsRecipientBounce(t *testing.T) {
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 1, 1}, "User unknown").IsRecipientBounce())
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 1, 2}, "Bad system").IsRecipientBounce())
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 1, 3}, "Bad syntax").IsRecipientBounce())
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 5, 0}, "Other").IsRecipientBounce())
	assert.False(t, NewSmtpResponseParser(550, [3]int{5, 7, 1}, "Spam").IsRecipientBounce())
	assert.False(t, NewSmtpResponseParser(450, [3]int{4, 7, 1}, "Rate limit").IsRecipientBounce())
	assert.False(t, NewSmtpResponseParser(550, [3]int{0, 0, 0}, "No enhanced code").IsRecipientBounce())
}

func TestSmtpResponseParser_IsInfrastructureError(t *testing.T) {
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 7, 1}, "Spam").IsInfrastructureError())
	assert.True(t, NewSmtpResponseParser(550, [3]int{5, 7, 9}, "Policy").IsInfrastructureError())
	assert.True(t, NewSmtpResponseParser(450, [3]int{4, 7, 1}, "Rate limit").IsInfrastructureError())
	assert.False(t, NewSmtpResponseParser(550, [3]int{5, 1, 1}, "User unknown").IsInfrastructureError())
	assert.False(t, NewSmtpResponseParser(550, [3]int{0, 0, 0}, "No enhanced code").IsInfrastructureError())
}

func TestSmtpResponseParser_BounceReason(t *testing.T) {
	assert.Equal(t, BounceReasonRecipient, NewSmtpResponseParser(550, [3]int{5, 1, 1}, "User unknown").BounceReason())
	assert.Equal(t, BounceReasonInfrastructure, NewSmtpResponseParser(550, [3]int{5, 7, 1}, "Spam").BounceReason())
	assert.Equal(t, BounceReason(""), NewSmtpResponseParser(250, [3]int{2, 0, 0}, "OK").BounceReason())
	assert.Equal(t, BounceReason(""), NewSmtpResponseParser(450, [3]int{4, 2, 1}, "Try later").BounceReason())
}

func TestSmtpResponseParser_GetFullMessage(t *testing.T) {
	assert.Equal(t, "550 5.1.1 User unknown", NewSmtpResponseParser(550, [3]int{5, 1, 1}, "User unknown").GetFullMessage())
	assert.Equal(t, "5.1.1 User unknown", NewSmtpResponseParser(0, [3]int{5, 1, 1}, "User unknown").GetFullMessage())
	assert.Equal(t, "550 User unknown", NewSmtpResponseParser(550, [3]int{0, 0, 0}, "User unknown").GetFullMessage())
	assert.Equal(t, "User unknown", NewSmtpResponseParser(0, [3]int{0, 0, 0}, "User unknown").GetFullMessage())
}
