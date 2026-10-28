package main

import (
	"context"
	"net/http"
	"net/http/httptest"
	"os"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestLocalApiSendContentStore_GetRaw(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		assert.Equal(t, "/api/local/sends/test-uuid/raw", r.URL.Path)
		assert.Equal(t, "GET", r.Method)
		w.Header().Set("Content-Type", "message/rfc822")
		w.Write([]byte("raw-email-content"))
	}))
	os.Setenv("GO_SYMFONY_URL", server.URL)
	defer os.Unsetenv("GO_SYMFONY_URL")
	defer server.Close()

	store, err := newSendContentStore()
	assert.NoError(t, err)
	assert.NotNil(t, store)

	raw, err := store.GetRaw(context.Background(), "test-uuid")
	assert.NoError(t, err)
	assert.Equal(t, "raw-email-content", raw)
}

func TestLocalApiSendContentStore_GetRaw_Error(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "Not Found", http.StatusNotFound)
	}))
	os.Setenv("GO_SYMFONY_URL", server.URL)
	defer os.Unsetenv("GO_SYMFONY_URL")
	defer server.Close()

	store, err := newSendContentStore()
	assert.NoError(t, err)

	raw, err := store.GetRaw(context.Background(), "missing-uuid")
	assert.Error(t, err)
	assert.Empty(t, raw)
}

func TestFetchContent_NilStore(t *testing.T) {
	worker := &EmailWorker{
		ctx:    context.Background(),
		logger: slogDiscard(),
	}

	raw, err := worker.fetchContent("some-uuid")
	assert.Error(t, err)
	assert.Empty(t, raw)
}
