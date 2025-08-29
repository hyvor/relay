package main

import (
	"io"
	"log/slog"
	"testing"

	"github.com/stretchr/testify/assert"
)

func slogDiscard() *slog.Logger {
	return slog.New(slog.NewTextHandler(io.Discard, nil))
}

func TestGetDomainFromEmail(t *testing.T) {

	var domain string
	domain = getDomainFromEmail("supun@hyvor.com")
	assert.Equal(t, "hyvor.com", domain)

	domain = getDomainFromEmail("john.doe@example.com")
	assert.Equal(t, "example.com", domain)

	domain = getDomainFromEmail("invalid-email")
	assert.Equal(t, "", domain)

}
