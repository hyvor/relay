package main

import (
	"context"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestNewSendContentStore_RequiresBucket(t *testing.T) {
	t.Setenv("S3_ENDPOINT", "http://localhost:8333")
	t.Setenv("S3_REGION", "us-east-1")
	t.Setenv("S3_KEY", "key")
	t.Setenv("S3_SECRET", "secret")
	t.Setenv("S3_BUCKET", "")

	store, err := newSendContentStore()
	assert.Error(t, err)
	assert.Nil(t, store)
}

func TestNewSendContentStore_Success(t *testing.T) {
	t.Setenv("S3_ENDPOINT", "http://localhost:8333")
	t.Setenv("S3_REGION", "us-east-1")
	t.Setenv("S3_KEY", "key")
	t.Setenv("S3_SECRET", "secret")
	t.Setenv("S3_BUCKET", "relay")

	store, err := newSendContentStore()
	assert.NoError(t, err)
	assert.NotNil(t, store)
	assert.Equal(t, "relay", store.bucket)
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
