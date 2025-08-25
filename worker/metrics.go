package main

import (
	"context"
	"log/slog"
	"net/http"
	"time"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

type MetricsServer struct {
	registry *prometheus.Registry
	logger   *slog.Logger
	ctx      context.Context

	serverStarted              bool
	isLeader                   bool
	cancelGlobalMetricsUpdater context.CancelFunc

	metrics *Metrics
}

type Metrics struct {
	// Global metrics ====
	// These are only exposed from the Leader server (the first registered server)
	relayInfo           *prometheus.GaugeVec
	emailQueueSize      *prometheus.GaugeVec
	pgsqlConnections    prometheus.Gauge
	pgsqlMaxConnections prometheus.Gauge
	serversTotal        prometheus.Gauge

	// Server (worker) metrics ====
	emailSendAttemptsTotal       *prometheus.CounterVec
	emailDeliveryDurationSeconds *prometheus.HistogramVec
	workersEmailTotal            prometheus.Gauge
	workersWebhookTotal          prometheus.Gauge
	webhookDeliveriesTotal       *prometheus.CounterVec
	incomingEmailsTotal          *prometheus.CounterVec
	dnsQueriesTotal              *prometheus.CounterVec
}

func NewMetricsServer(ctx context.Context, logger *slog.Logger) *MetricsServer {

	registry := prometheus.NewRegistry()

	metrics := &MetricsServer{
		registry: registry,
		logger:   logger.With("component", "metrics_server"),
		ctx:      ctx,
		metrics:  newMetrics(),
	}

	return metrics

}

func newMetrics() *Metrics {
	return &Metrics{
		// Global metrics
		relayInfo: prometheus.NewGaugeVec(
			prometheus.GaugeOpts{
				Name: "relay_info",
				Help: "Information about the relay instance",
			},
			[]string{
				"version",
				"env",
				"instance_domain",
			},
		),
		emailQueueSize: prometheus.NewGaugeVec(
			prometheus.GaugeOpts{
				Name: "email_queue_size",
				Help: "Number of pending emails in the queue",
			},
			[]string{"queue_name"},
		),
		pgsqlConnections: prometheus.NewGauge(
			prometheus.GaugeOpts{
				Name: "pgsql_connections",
				Help: "Number of active PostgreSQL connections",
			},
		),
		pgsqlMaxConnections: prometheus.NewGauge(
			prometheus.GaugeOpts{
				Name: "pgsql_max_connections",
				Help: "Maximum number of PostgreSQL connections",
			},
		),
		serversTotal: prometheus.NewGauge(
			prometheus.GaugeOpts{
				Name: "servers_total",
				Help: "Total number of registered servers in this Hyvor Relay instance",
			},
		),

		// Server (worker) metrics
		emailSendAttemptsTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "email_send_attempts_total",
				Help: "Total number of email send attempts",
			},
			[]string{"queue_name", "ip", "status"},
		),
		emailDeliveryDurationSeconds: prometheus.NewHistogramVec(
			prometheus.HistogramOpts{
				Name:    "email_delivery_duration_seconds",
				Help:    "Duration of email delivery in seconds",
				Buckets: prometheus.DefBuckets,
			},
			[]string{"queue_name", "ip"},
		),
		workersEmailTotal: prometheus.NewGauge(
			prometheus.GaugeOpts{
				Name: "workers_email_total",
				Help: "Total number of email workers",
			},
		),
		workersWebhookTotal: prometheus.NewGauge(
			prometheus.GaugeOpts{
				Name: "workers_webhook_total",
				Help: "Total number of webhook workers",
			},
		),
		webhookDeliveriesTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "webhook_deliveries_total",
				Help: "Total number of webhook deliveries",
			},
			// success, failed, deferred
			[]string{"status"},
		),
		incomingEmailsTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "incoming_emails_total",
				Help: "Total number of incoming emails",
			},
			// type = "bounce", "fbl", "unknown"
			[]string{"type"},
		),
		dnsQueriesTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "dns_queries_total",
				Help: "Total number of DNS queries handled",
			},
			// status = "found", "not_found"
			[]string{"type", "status"},
		),
	}
}

func (server *MetricsServer) Set(goState GoState) {

	server.isLeader = goState.IsLeader

	// unregister all
	// important: make sure to unregister everything!
	server.registry.Unregister(server.metrics.relayInfo)
	server.registry.Unregister(server.metrics.emailQueueSize)
	server.registry.Unregister(server.metrics.pgsqlConnections)
	server.registry.Unregister(server.metrics.pgsqlMaxConnections)
	server.registry.Unregister(server.metrics.serversTotal)
	server.registry.Unregister(server.metrics.emailSendAttemptsTotal)
	server.registry.Unregister(server.metrics.emailDeliveryDurationSeconds)
	server.registry.Unregister(server.metrics.workersEmailTotal)
	server.registry.Unregister(server.metrics.workersWebhookTotal)
	server.registry.Unregister(server.metrics.webhookDeliveriesTotal)
	server.registry.Unregister(server.metrics.incomingEmailsTotal)
	server.registry.Unregister(server.metrics.dnsQueriesTotal)

	// register global metrics if the current server is the leader
	if goState.IsLeader {
		server.registry.MustRegister(
			server.metrics.relayInfo,
			server.metrics.emailQueueSize,
			server.metrics.pgsqlConnections,
			server.metrics.pgsqlMaxConnections,
			server.metrics.serversTotal,
		)
	}

	// register all server metrics
	server.registry.MustRegister(server.metrics.emailSendAttemptsTotal)
	server.registry.MustRegister(server.metrics.emailDeliveryDurationSeconds)
	server.registry.MustRegister(server.metrics.workersEmailTotal)
	server.registry.MustRegister(server.metrics.workersWebhookTotal)
	server.registry.MustRegister(server.metrics.webhookDeliveriesTotal)
	server.registry.MustRegister(server.metrics.incomingEmailsTotal)
	server.registry.MustRegister(server.metrics.dnsQueriesTotal)

	// Set static values
	server.metrics.relayInfo.WithLabelValues(
		goState.Version,
		goState.Env,
		goState.InstanceDomain,
	).Set(1)
	server.metrics.workersEmailTotal.Set(float64(len(goState.Ips) * goState.EmailWorkersPerIp))
	server.metrics.workersWebhookTotal.Set(float64(goState.WebhookWorkers))
	server.metrics.serversTotal.Set(float64(goState.ServersCount))

	if !server.serverStarted {
		server.serverStarted = true

		go func() {

			handler := promhttp.HandlerFor(server.registry, promhttp.HandlerOpts{})

			mux := http.NewServeMux()
			mux.Handle("/", handler)
			mux.Handle("/metrics", handler)

			server.logger.Info("Starting metrics server on :9667")

			if err := http.ListenAndServe(":9667", mux); err != nil {
				server.logger.Error("Failed to start metrics server", "error", err)
			}

		}()

		go func() {
			<-server.ctx.Done()
			server.logger.Info("Shutting down metrics server")
		}()
	}

	server.StartGlobalMetricsUpdater()

}

// stops and restarts the global metrics updater
func (server *MetricsServer) StartGlobalMetricsUpdater() {

	if server.cancelGlobalMetricsUpdater != nil {
		server.cancelGlobalMetricsUpdater()
	}

	ctx, cancel := context.WithCancel(server.ctx)
	server.cancelGlobalMetricsUpdater = cancel

	if !server.isLeader {
		return
	}

	go func() {
		for {
			select {
			case <-ctx.Done():
				return
			default:
				server.updateGlobalMetrics()
				time.Sleep(10 * time.Second)
			}
		}
	}()

}

func (server *MetricsServer) updateGlobalMetrics() {

	server.logger.Info("Starting to update global metrics")

	conn, err := NewDbConn(LoadDBConfig())

	if err != nil {
		server.logger.Error("Failed to connect to PostgreSQL to update metrics", "error", err)
		return
	}

	defer conn.Close()

	// connections
	var connections int
	err = conn.QueryRow("SELECT count(*) FROM pg_stat_activity").Scan(&connections)
	if err != nil {
		server.logger.Error("Failed to get PostgreSQL connections", "error", err)
		return
	}
	server.metrics.pgsqlConnections.Set(float64(connections))

	// max connections
	var maxConnections int
	err = conn.QueryRow("SELECT setting::int FROM pg_settings WHERE name = 'max_connections'").Scan(&maxConnections)
	if err != nil {
		server.logger.Error("Failed to get PostgreSQL max connections", "error", err)
		return
	}
	server.metrics.pgsqlMaxConnections.Set(float64(maxConnections))

	// email queue size
	rows, err := conn.Query(`
		SELECT count(sends.id), queues.name
		FROM sends
		INNER JOIN queues ON sends.queue_id = queues.id
		WHERE sends.status = 'queued'
		AND sends.send_after < NOW()
		GROUP BY queues.name
	`)

	if err != nil {
		server.logger.Error("Failed to get email queue size", "error", err)
		return
	}

	for rows.Next() {
		var count int
		var queueName string

		if err := rows.Scan(&count, &queueName); err != nil {
			server.logger.Error("Failed to scan queue size", "error", err)
			continue
		}

		server.metrics.emailQueueSize.WithLabelValues(queueName).Set(float64(count))
	}

	if err := rows.Close(); err != nil {
		server.logger.Error("Failed to close rows", "error", err)
		return
	}

}
