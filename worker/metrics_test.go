package main

import (
	"context"
	"io"
	"net/http"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestCreateMetricsServer(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	logger := slogDiscard()

	metricsServer := NewMetricsServer(ctx, logger)
	assert.NotNil(t, metricsServer)

}

func TestMetricsHttpServerNotLeader(t *testing.T) {

	t.Parallel()

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	logger := slogDiscard()

	metricsServer := NewMetricsServer(ctx, logger)
	metricsServer.Set(GoState{
		IsLeader: false, // so it does not connect to the database
	})

	assert.True(t, metricsServer.serverStarted)

	metricsServer.metrics.emailSendAttemptsTotal.WithLabelValues("test_queue", "127.0.0.1", "success").Inc()
	metricsServer.metrics.workersEmailTotal.Set(100)

	time.Sleep(100 * time.Millisecond) // Wait for the HTTP server to start

	resp, err := http.Get("http://localhost:9667/metrics")
	assert.NoError(t, err)
	defer resp.Body.Close()

	assert.Equal(t, http.StatusOK, resp.StatusCode)

	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "email_send_attempts_total{ip=\"127.0.0.1\",queue_name=\"test_queue\",status=\"success\"} 1")
	assert.Contains(t, string(body), "workers_email_total 100")
	assert.NotContains(t, string(body), "relay_info") // Not a leader, so no global metrics

	assert.Nil(t, metricsServer.cancelGlobalMetricsUpdater)

}

func TestMetricsHttpServerLeader(t *testing.T) {

	t.Parallel()

	loadEnvFiles()

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	logger := slogDiscard()

	metricsServer := NewMetricsServer(ctx, logger)
	metricsServer.Set(GoState{
		IsLeader:       true,
		Env:            "test",
		Version:        "1.0.0",
		InstanceDomain: "relay.hyvor.com",
	})

	assert.True(t, metricsServer.serverStarted)

	time.Sleep(100 * time.Millisecond)

	resp, err := http.Get("http://localhost:9667/metrics")
	assert.NoError(t, err)
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "relay_info{env=\"test\",instance_domain=\"relay.hyvor.com\",version=\"1.0.0\"} 1")

}
