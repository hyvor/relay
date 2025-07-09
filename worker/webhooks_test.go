package main

import (
	"bytes"
	"context"
	"io"
	"log/slog"
	"sync"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestNewWebhookWorkersPool(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, nil))
	pool := NewWebhookWorkersPool(ctx, logger)

	assert.NotNil(t, pool)
	assert.Equal(t, ctx, pool.ctx)
	assert.Nil(t, pool.cancelFunc)

	cancel()
	time.Sleep(10 * time.Millisecond)

	assert.Contains(t, buf.String(), "Stopping webhook workers pool")
}

func TestWebhookWorkersPoolSet(t *testing.T) {

	canceled := false
	cancelFunc := func() {
		canceled = true
	}

	var called []int
	var mu sync.Mutex
	mockWorker := func(ctx context.Context, id int, wg *sync.WaitGroup, config *DBConfig, logger *slog.Logger, instanceDomain string) {
		defer wg.Done()
		mu.Lock()
		called = append(called, id)
		mu.Unlock()
	}

	pool := &WebhookWorkersPool{
		ctx:        context.Background(),
		cancelFunc: cancelFunc,
		workerFunc: mockWorker,
		logger:     slog.New(slog.NewTextHandler(io.Discard, nil)),
	}

	pool.Set(2, "relay.hyvor.com")

	time.Sleep(20 * time.Millisecond)

	assert.True(t, canceled)
	assert.Equal(t, 2, len(called))

}

func TestWebhookWorkersPoolStopWorkers(t *testing.T) {

	canceled := false
	cancelFunc := func() {
		canceled = true
	}

	pool := &WebhookWorkersPool{
		ctx:        context.Background(),
		cancelFunc: cancelFunc,
	}

	pool.StopWorkers()

	time.Sleep(10 * time.Millisecond)

	assert.True(t, canceled)
	assert.Nil(t, pool.cancelFunc)

}

// worker testing
