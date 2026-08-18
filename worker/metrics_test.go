package main

import (
	"context"
	"io"
	"net/http"
	"testing"
	"time"

	"github.com/prometheus/client_golang/prometheus"
	dto "github.com/prometheus/client_model/go"
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

	metricsPort = ":61000"

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

	resp, err := http.Get("http://localhost:61000/metrics")
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

	metricsPort = ":61001"

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

	resp, err := http.Get("http://localhost:61001/metrics")
	assert.NoError(t, err)
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	assert.Contains(t, string(body), "relay_info{env=\"test\",instance_domain=\"relay.hyvor.com\",version=\"1.0.0\"} 1")

}

func TestIsIpApproved(t *testing.T) {
	tests := []struct {
		remoteAddr   string
		expected     bool
	}{
		{"127.0.0.1:1234", true},
		{"[::1]:5678", true},
		{"192.168.1.5:8080", true},
		{"10.1.2.3:9999", true},
		{"172.16.5.5:8080", true},
		{"100.64.0.1:8080", true}, // CGNAT
		{"8.8.8.8:1234", false},   // public IP
		{"8.8.8.8:1234", false},  // public IP,
	}

	for _, tt := range tests {
		req := &http.Request{
			RemoteAddr: tt.remoteAddr,
		}
		got := isPrivateIp(req)
		if got != tt.expected {
			t.Errorf("isPrivateIp(%s) = %v; expected %v", tt.remoteAddr, got, tt.expected)
		}
	}
}

func TestWorkerGaugesAreNotOverwrittenByState(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	metricsServer := NewMetricsServer(ctx, slogDiscard())

	// pretend three email workers, two webhook workers and one incoming
	// worker actually started
	metricsServer.metrics.workersEmailTotal.Add(3)
	metricsServer.metrics.workersWebhookTotal.Add(2)
	metricsServer.metrics.workersIncomingTotal.Add(1)

	// a state update asking for far more workers must not move the gauges:
	// the goroutines have not started yet, so reporting them would be a lie
	metricsServer.Set(GoState{
		IsLeader:          false,
		Ips:               []GoStateIp{{Id: 1}, {Id: 2}},
		EmailWorkersPerIp: 50,
		WebhookWorkers:    50,
		IncomingWorkers:   50,
		ApiWorkers:        7,
	})

	assert.Equal(t, 3.0, gaugeValue(t, metricsServer.metrics.workersEmailTotal))
	assert.Equal(t, 2.0, gaugeValue(t, metricsServer.metrics.workersWebhookTotal))
	assert.Equal(t, 1.0, gaugeValue(t, metricsServer.metrics.workersIncomingTotal))

	// API workers are PHP processes this binary does not own, so that gauge
	// is still reported from the state
	assert.Equal(t, 7.0, gaugeValue(t, metricsServer.metrics.workersApiTotal))

}

func gaugeValue(t *testing.T, gauge prometheus.Gauge) float64 {
	t.Helper()

	metric := &dto.Metric{}
	assert.NoError(t, gauge.Write(metric))

	return metric.GetGauge().GetValue()
}
