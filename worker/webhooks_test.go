package main

import (
	"bytes"
	"context"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/suite"
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
	mockWorker := func(ctx context.Context, id int, wg *sync.WaitGroup, config *DBConfig, logger *slog.Logger) {
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

	pool.Set(2)

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

type WebhookWorkerTestSuite struct {
	suite.Suite
}

func (suite *WebhookWorkerTestSuite) SetupTest() {
	err := truncateTestDb()
	suite.NoError(err, "Failed to truncate test database")
}

func TestExampleTestSuite(t *testing.T) {
	suite.Run(t, new(WebhookWorkerTestSuite))
}

func (suite *WebhookWorkerTestSuite) TestWhenNoWebhookDeliveriesFound() {
	ctx, cancel := context.WithCancel(context.Background())

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, &slog.HandlerOptions{
		Level: slog.LevelDebug,
	}))

	var wg sync.WaitGroup
	wg.Add(2)
	go webhookWorker(ctx, 1, &wg, getTestDbConfig(), logger)
	go func() {
		defer wg.Done()
		time.Sleep(50 * time.Millisecond)
		cancel()
	}()
	wg.Wait()

	suite.Contains(buf.String(), "Worker found no webhook deliveries")
}

func (suite *WebhookWorkerTestSuite) TestWebhookDeliverySent() {

	server := httptest.NewServer(http.HandlerFunc(func(rw http.ResponseWriter, req *http.Request) {
		suite.Equal(req.URL.String(), "/webhook")
		rw.Write([]byte(`OK`))
	}))
	defer server.Close()

	ctx, cancel := context.WithCancel(context.Background())

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, &slog.HandlerOptions{
		Level: slog.LevelDebug,
	}))

	factory, err := NewTestFactory()
	suite.NoError(err, "Failed to create test factory")

	deliveryId, err := factory.WebhookDelivery(server.URL+"/webhook", `{"key": "value"}`)
	suite.NoError(err, "Failed to create webhook delivery")

	var wg sync.WaitGroup
	wg.Add(2)
	go webhookWorker(ctx, 1, &wg, getTestDbConfig(), logger)
	go func() {
		defer wg.Done()
		time.Sleep(100 * time.Millisecond)
		cancel()
	}()
	wg.Wait()

	suite.Contains(buf.String(), "Worker found webhook deliveries")
	suite.Contains(buf.String(), "count=1")
	suite.Contains(buf.String(), "Worker finalized webhook delivery")

	delivery, err := getWebhookDeliveryEntityById(factory.conn, deliveryId)
	suite.NoError(err, "Failed to get webhook delivery by ID")

	suite.Equal("delivered", delivery.Status)
	suite.Equal("OK", delivery.Response.String)
	suite.Equal(200, int(delivery.ResponseCode.Int64))
	suite.Equal(1, delivery.TryCount)
}

func (suite *WebhookWorkerTestSuite) TestWebhookDeliveryRequeuedOnFailure() {
	server := httptest.NewServer(http.HandlerFunc(func(rw http.ResponseWriter, req *http.Request) {
		suite.Equal(req.URL.String(), "/webhook")
		rw.WriteHeader(500)
		rw.Write([]byte(`Internal Server Error`))
	}))
	defer server.Close()

	ctx, cancel := context.WithCancel(context.Background())

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, &slog.HandlerOptions{
		Level: slog.LevelDebug,
	}))

	factory, err := NewTestFactory()
	suite.NoError(err, "Failed to create test factory")

	deliveryId, err := factory.WebhookDelivery(server.URL+"/webhook", `{"key": "value"}`)
	suite.NoError(err, "Failed to create webhook delivery")

	var wg sync.WaitGroup
	wg.Add(2)
	go webhookWorker(ctx, 1, &wg, getTestDbConfig(), logger)
	go func() {
		defer wg.Done()
		time.Sleep(100 * time.Millisecond)
		cancel()
	}()
	wg.Wait()

	delivery, err := getWebhookDeliveryEntityById(factory.conn, deliveryId)
	suite.NoError(err, "Failed to get webhook delivery by ID")

	suite.Equal("pending", delivery.Status)
	suite.Equal("Internal Server Error", delivery.Response.String)
	suite.Equal(500, int(delivery.ResponseCode.Int64))
	suite.Equal(1, delivery.TryCount)
}
