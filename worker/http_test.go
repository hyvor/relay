package main

import (
	"context"
	"encoding/base64"
	"io"
	"log/slog"
	"net/http"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestPingAndReady(t *testing.T) {

	context, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(context, serviceState)

	time.Sleep(100 * time.Millisecond)

	resp, err := http.Get("http://localhost:8085/ping")
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Equal(t, "ok", string(body))

	resp, err = http.Get("http://localhost:8085/ready")
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, 503, resp.StatusCode)
	body, _ = io.ReadAll(resp.Body)
	assert.Equal(t, "not ready", string(body))

	serviceState.IsSet = true
	resp, err = http.Get("http://localhost:8085/ready")
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ = io.ReadAll(resp.Body)
	assert.Equal(t, "ready", string(body))

}

// ========== /state ==========

func TestSetState(t *testing.T) {
	context, cancel := context.WithCancel(context.Background())
	defer cancel()

	fakeLogger := slog.New(slog.NewTextHandler(io.Discard, nil))
	serviceState := NewServiceState(context, fakeLogger)
	StartHttpServer(context, serviceState)

	time.Sleep(100 * time.Millisecond)

	jsonData := `{"is_set": true}`

	resp, err := http.Post("http://localhost:8085/state", "application/json", strings.NewReader(jsonData))
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "Go state updated")

	assert.True(t, serviceState.IsSet)

}

// ========== /debug/parse-bounce-fbl ==========

func TestDebugParseBounce(t *testing.T) {
	context, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
	}
	StartHttpServer(context, serviceState)

	time.Sleep(1200 * time.Millisecond)

	raw := []byte(`To: <bounce@relay.hyvor.com>
Content-Type: multipart/report; report-type=delivery-status;
    boundary="myboundary"

--myboundary

Invalid email

--myboundary
Content-Type: message/delivery-status

Reporting-MTA: dns; google.com

Original-Recipient: rfc822;test@hyvor.com
Final-Recipient: rfc822;test@hyvor.com
Action: failed
Status: 4.0.0

--myboundary
Content-Type: message/rfc822

[original message goes here]

--myboundary--`)
	rawBase64 := base64.StdEncoding.EncodeToString(raw)
	jsonData := `{"raw": "` + rawBase64 + `","type": "bounce"}`

	resp, err := http.Post("http://localhost:8085/debug/parse-bounce-fbl", "application/json", strings.NewReader(jsonData))
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "Invalid email")
	assert.Contains(t, string(body), "4.0.0")
	assert.Contains(t, string(body), "failed")

}

func TestDebugParseFbl(t *testing.T) {
	context, cancel := context.WithCancel(context.Background())
	defer cancel()
	serviceState := &ServiceState{
		Logger: slogDiscard(),
	}
	StartHttpServer(context, serviceState)

	time.Sleep(100 * time.Millisecond)

	content, err := os.ReadFile("./bounceparse/testdata/arf1.txt")
	assert.NoError(t, err)
	rawBase64 := base64.StdEncoding.EncodeToString(content)
	jsonData := `{"raw": "` + rawBase64 + `","type": "fbl"}`

	resp, err := http.Post("http://localhost:8085/debug/parse-bounce-fbl", "application/json", strings.NewReader(jsonData))
	assert.NoError(t, err)
	defer resp.Body.Close()
	assert.Equal(t, http.StatusOK, resp.StatusCode)
	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "abuse")
	assert.Contains(t, string(body), "SomeGenerator/1.0")
	assert.Contains(t, string(body), "somespammer@example.net")
	assert.Contains(t, string(body), "8787KJKJ3K4J3K4J3K4J3.mail@example.net")
	assert.Contains(t, string(body), "This is an email abuse report for an email message received from IP")

}
