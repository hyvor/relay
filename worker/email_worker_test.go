package main

import (
	"bytes"
	"context"
	"database/sql"
	"errors"
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
	pool := NewEmailWorkersPool(ctx, logger, newMetrics())

	assert.NotNil(t, pool)
	assert.Equal(t, ctx, pool.ctx)
	assert.Nil(t, pool.cancelFunc)

	cancel()
	time.Sleep(10 * time.Millisecond)

	assert.Contains(t, buf.String(), "Stopping email workers pool")
}

// func TestEmailWorkersPoolSet(t *testing.T) {

// 	canceled := false
// 	cancelFunc := func() {
// 		canceled = true
// 	}

// 	var called []int
// 	var mu sync.Mutex
// 	mockWorker := func(ctx context.Context, id int, wg *sync.WaitGroup, config *DBConfig, logger *slog.Logger, metrics *Metrics, ip GoStateIp, instanceDomain string) {
// 		defer wg.Done()
// 		mu.Lock()
// 		called = append(called, id)
// 		mu.Unlock()
// 	}

// 	pool := &EmailWorkersPool{
// 		ctx:        context.Background(),
// 		cancelFunc: cancelFunc,
// 		workerFunc: mockWorker,
// 		logger:     slog.New(slog.NewTextHandler(io.Discard, nil)),
// 	}

// 	pool.Set([]GoStateIp{
// 		{Ip: "1.1.1.1", QueueId: 1, QueueName: "transactional"},
// 		{Ip: "2.2.2.2", QueueId: 2, QueueName: "distributional"},
// 	}, 2, "relay.hyvor.com")

// 	time.Sleep(20 * time.Millisecond)

// 	assert.True(t, canceled)
// 	assert.Equal(t, 2, len(called))

// }

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

// worker testing

func TestEmailWorker_DatabaseConnectionFailure(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())

	var wg sync.WaitGroup
	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, nil))

	dbConfig := &DBConfig{
		Host:     "localhost",
		Port:     "5432",
		User:     "test",
		Password: "test",
		DBName:   "test",
		SSLMode:  "disable",
	}

	ip := GoStateIp{
		Ip:        "1.1.1.1",
		QueueId:   1,
		QueueName: "test",
	}

	originalNewDbConn := NewDbConn
	NewDbConn = func(config *DBConfig) (*sql.DB, error) {
		return nil, errors.New("connection failed")
	}
	defer func() { NewDbConn = originalNewDbConn }()

	wg.Add(2)

	emailWorker := NewEmailWorker(
		ctx,
		1,
		&wg,
		dbConfig,
		logger,
		newMetrics(),
		ip,
		"relay.hyvor.com",
	)
	go emailWorker.Start()
	go func() {
		defer wg.Done()
		time.Sleep(40 * time.Millisecond) // Simulate some work
		cancel()                          // Cancel the context to stop the worker
	}()
	wg.Wait()

	assert.Contains(t, buf.String(), "Failed to connect to database, retrying")
	assert.Contains(t, buf.String(), "connection failed")
}

func TestEmailWorker_CallsProcessSend(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())

	calledTimes := 0

	var wg sync.WaitGroup
	wg.Add(2)
	emailWorker := &EmailWorker{
		ctx:      ctx,
		dbConfig: getTestDbConfig(),
		ProcessSendFunc: func(conn *sql.DB) {
			calledTimes++
			wg.Done()
			time.Sleep(10 * time.Millisecond)
		},
	}

	go func() {
		defer wg.Done()
		time.Sleep(50 * time.Millisecond)
		cancel()
	}()

	go emailWorker.Start()
	wg.Wait()

	assert.Greater(t, calledTimes, 0)
}

func TestEmailWorker_ProcessSend(t *testing.T) {

	//

}
