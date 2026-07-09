package main

import (
	"context"
	"os"
	"path/filepath"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestNewSendContentStore_S3RequiresBucket(t *testing.T) {
	t.Setenv("FILESYSTEM", "s3")
	t.Setenv("S3_ENDPOINT", "http://localhost:8333")
	t.Setenv("S3_REGION", "us-east-1")
	t.Setenv("S3_KEY", "key")
	t.Setenv("S3_SECRET", "secret")
	t.Setenv("S3_BUCKET", "")

	store, err := newSendContentStore()
	assert.Error(t, err)
	assert.Nil(t, store)
}

func TestNewSendContentStore_S3Success(t *testing.T) {
	t.Setenv("FILESYSTEM", "s3")
	t.Setenv("S3_ENDPOINT", "http://localhost:8333")
	t.Setenv("S3_REGION", "us-east-1")
	t.Setenv("S3_KEY", "key")
	t.Setenv("S3_SECRET", "secret")
	t.Setenv("S3_BUCKET", "relay")

	store, err := newSendContentStore()
	assert.NoError(t, err)
	assert.NotNil(t, store)

	s3Store, ok := store.(*s3SendContentStore)
	assert.True(t, ok)
	assert.Equal(t, "relay", s3Store.bucket)
}

func TestNewSendContentStore_FileSuccess(t *testing.T) {
	t.Setenv("FILESYSTEM", "file")

	store, err := newSendContentStore()
	assert.NoError(t, err)
	assert.NotNil(t, store)
	assert.IsType(t, &localSendContentStore{}, store)
}

func TestNewSendContentStore_UnsupportedFilesystem(t *testing.T) {
	t.Setenv("FILESYSTEM", "gcs")

	store, err := newSendContentStore()
	assert.Error(t, err)
	assert.Nil(t, store)
}

func TestLocalSendContentStore_GetRaw(t *testing.T) {
	root := t.TempDir()
	uuid := "some-uuid"

	sendsDir := filepath.Join(root, "sends")
	assert.NoError(t, os.MkdirAll(sendsDir, 0o755))
	assert.NoError(t, os.WriteFile(filepath.Join(sendsDir, uuid+".eml"), []byte("raw-mime-content"), 0o644))

	store := &localSendContentStore{root: root}

	raw, err := store.GetRaw(context.Background(), uuid)
	assert.NoError(t, err)
	assert.Equal(t, "raw-mime-content", raw)
}

func TestLocalSendContentStore_GetRaw_Missing(t *testing.T) {
	store := &localSendContentStore{root: t.TempDir()}

	raw, err := store.GetRaw(context.Background(), "missing")
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
