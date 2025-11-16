package main

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"

	"github.com/hyvor/relay/worker/smtp_interface"
	"github.com/stretchr/testify/assert"
)

func TestLocalApiUrl(t *testing.T) {
	os.Unsetenv("GO_SYMFONY_URL")
	url1 := getSymfonyUrl("/api/local/test-endpoint")
	assert.Equal(t, "http://localhost:80/api/local/test-endpoint", url1)

	os.Setenv("GO_SYMFONY_URL", "http://example.com")
	url2 := getSymfonyUrl("/api/local/another-endpoint")
	assert.Equal(t, "http://example.com/api/local/another-endpoint", url2)

	os.Unsetenv("GO_SYMFONY_URL")
}

func TestHandleCallLocalApi(t *testing.T) {

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"status":"ok"}`))
	}))
	os.Setenv("GO_SYMFONY_URL", server.URL)
	defer os.Unsetenv("GO_SYMFONY_URL")
	defer server.Close()

	ctx := context.Background()
	body := map[string]string{"key": "value"}
	var response map[string]interface{}

	err := handleCallLocalApi(
		ctx,
		"GET",
		"/test-endpoint",
		body,
		&response,
	)
	assert.NoError(t, err)
	assert.Equal(t, "ok", response["status"])

}

func TestHandleInvalidStatusCode(t *testing.T) {

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "Bad Request", http.StatusBadRequest)
	}))
	os.Setenv("GO_SYMFONY_URL", server.URL)
	defer os.Unsetenv("GO_SYMFONY_URL")
	defer server.Close()

	ctx := context.Background()
	var response map[string]interface{}

	err := handleCallLocalApi(
		ctx,
		"GET",
		"/test-endpoint",
		nil,
		&response,
	)
	assert.Error(t, err)
	assert.Equal(
		t,
		"unexpected status code: GET "+server.URL+"/api/local/test-endpoint 400 Bad Request",
		strings.TrimSpace(err.Error()),
	)
}


func TestHandleCallSendEmailApi(t *testing.T) {


	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {

		authHeader := r.Header.Get("Authorization")
		expectedAuthHeader := "Bearer my-api-key"
		assert.Equal(t, expectedAuthHeader, authHeader)

		body, err := io.ReadAll(r.Body)
		assert.NoError(t, err)

		expectedBodySubstring := `"from":{"email":"sender@example.com"}`
		assert.Contains(t, string(body), expectedBodySubstring)

		expectedBodySubstring = `"to":[{"email":"recipient@example.com"}]`
		assert.Contains(t, string(body), expectedBodySubstring)

		expectedBodySubstring = `"subject":"Test Email"`
		assert.Contains(t, string(body), expectedBodySubstring)

		expectedBodySubstring = `"body_html":"\u003cp\u003eThis is a test email.\u003c/p\u003e"`
		assert.Contains(t, string(body), expectedBodySubstring)

		expectedBodySubstring = `"body_text":"This is a test email."`
		assert.Contains(t, string(body), expectedBodySubstring)

		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"status":"ok"}`))
	}))

	os.Setenv("GO_SYMFONY_URL", server.URL)
	defer os.Unsetenv("GO_SYMFONY_URL")
	defer server.Close()

	ctx := context.Background()

	apiRequest := &smtp_interface.ApiRequest{
		From: smtp_interface.Address{
			Email: "sender@example.com",
		},
		To: []smtp_interface.Address{
			{Email: "recipient@example.com"},
		},
		Subject: "Test Email",
		BodyHtml: "<p>This is a test email.</p>",
		BodyText: "This is a test email.",
	}

	err := handleCallSendEmailApi(
		ctx,
		"my-api-key",
		apiRequest,
	)
	assert.NoError(t, err)

}