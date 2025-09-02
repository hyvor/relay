package main

import (
	"context"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestLocalApiUrl(t *testing.T) {
	os.Unsetenv("GO_SYMFONY_URL")
	url1 := localApiUrl("/test-endpoint")
	assert.Equal(t, "http://localhost:80/api/local/test-endpoint", url1)

	os.Setenv("GO_SYMFONY_URL", "http://example.com")
	url2 := localApiUrl("/another-endpoint")
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
