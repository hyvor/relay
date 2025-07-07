package main

import (
	"bytes"
	"context"
	"log/slog"
	"sync"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestNewEmailWorkersPool(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, nil))
	pool := NewEmailWorkersPool(ctx, logger)

	assert.NotNil(t, pool)
	assert.Equal(t, ctx, pool.ctx)
	assert.Nil(t, pool.cancelFunc)

	cancel()
	time.Sleep(10 * time.Millisecond)

	assert.Contains(t, buf.String(), "Stopping email workers pool")
}

func TestEmailWorkersPoolSet(t *testing.T) {

	canceled := false
	cancelFunc := func() {
		canceled = true
	}

	var called []int
	var mu sync.Mutex
	mockWorker := func(ctx context.Context, id int, wg *sync.WaitGroup, config *DBConfig, logger *slog.Logger, ip GoStateIp) {
		defer wg.Done()
		mu.Lock()
		called = append(called, id)
		mu.Unlock()
	}

	pool := &EmailWorkersPool{
		ctx:        context.Background(),
		cancelFunc: cancelFunc,
		workerFunc: mockWorker,
	}

	pool.Set([]GoStateIp{
		{Ip: "1.1.1.1", QueueId: 1, QueueName: "transactional"},
		{Ip: "2.2.2.2", QueueId: 2, QueueName: "distributional"},
	}, 2)

	time.Sleep(20 * time.Millisecond)

	assert.True(t, canceled)
	assert.Equal(t, 2, len(called))

}

func TestEmailWorkersPoolStopWorkers(t *testing.T) {

	canceled := false
	cancelFunc := func() {
		canceled = true
	}

	pool := &EmailWorkersPool{
		ctx:        context.Background(),
		cancelFunc: cancelFunc,
	}

	pool.StopWorkers()

	time.Sleep(10 * time.Millisecond)

	assert.True(t, canceled)
	assert.Nil(t, pool.cancelFunc)

}
