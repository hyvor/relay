package main

import (
	"context"
	"log/slog"
	"net/http"
	"time"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

type Metrics struct {
	registry *prometheus.Registry
	logger   *slog.Logger
	ctx      context.Context

	serverStarted bool
	isLeader      bool

	// Global metrics ====
	// These are only exposed from the Leader server (the first registered server)
	relayInfo           *prometheus.GaugeVec
	emailQueueSize      *prometheus.GaugeVec
	pgsqlConnections    prometheus.Gauge
	pgsqlMaxConnections prometheus.Gauge
	serversTotal        prometheus.Gauge

	// Instance metrics ====
	emailSendAtteptsTotal        *prometheus.CounterVec
	emailDeferredTotal           *prometheus.CounterVec
	emailAcceptedTotal           *prometheus.CounterVec
	emailBouncedTotal            *prometheus.CounterVec
	emailDeliveryDurationSeconds *prometheus.HistogramVec
	workersEmailTotal            prometheus.Gauge
	workersWebhookTotal          prometheus.Gauge
}

func NewMetrics(ctx context.Context, logger *slog.Logger) *Metrics {

	registry := prometheus.NewRegistry()

	metrics := &Metrics{
		registry: registry,
		logger:   logger,
		ctx:      ctx,

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

		// Instance metrics
		emailSendAtteptsTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "email_send_attempts_total",
				Help: "Total number of email send attempts",
			},
			[]string{"queue_name", "ip"},
		),
		emailDeferredTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "email_deferred_total",
				Help: "Total number of deferred emails",
			},
			[]string{"queue_name", "ip"},
		),
		emailAcceptedTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "email_accepted_total",
				Help: "Total number of accepted emails",
			},
			[]string{"queue_name", "ip"},
		),
		emailBouncedTotal: prometheus.NewCounterVec(
			prometheus.CounterOpts{
				Name: "email_bounced_total",
				Help: "Total number of bounced emails",
			},
			[]string{"queue_name", "ip"},
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
	}

	return metrics

}

func (m *Metrics) Set(goState GoState) {

	m.isLeader = goState.IsLeader

	if goState.IsLeader {
		m.registry.MustRegister(
			m.relayInfo,
			m.emailQueueSize,
			m.pgsqlConnections,
			m.pgsqlMaxConnections,
			m.serversTotal,
		)
	} else {
		m.registry.Unregister(m.relayInfo)
		m.registry.Unregister(m.emailQueueSize)
		m.registry.Unregister(m.pgsqlConnections)
		m.registry.Unregister(m.pgsqlMaxConnections)
		m.registry.Unregister(m.serversTotal)
	}

	// register all instance metrics
	m.registry.MustRegister(m.emailSendAtteptsTotal)
	m.registry.MustRegister(m.emailDeferredTotal)
	m.registry.MustRegister(m.emailAcceptedTotal)
	m.registry.MustRegister(m.emailBouncedTotal)
	m.registry.MustRegister(m.emailDeliveryDurationSeconds)
	m.registry.MustRegister(m.workersEmailTotal)
	m.registry.MustRegister(m.workersWebhookTotal)

	// Set static values
	m.relayInfo.WithLabelValues(
		goState.Version,
		goState.Env,
		goState.InstanceDomain,
	).Set(1)
	m.workersEmailTotal.Set(float64(len(goState.Ips) * goState.EmailWorkersPerIp))
	m.workersWebhookTotal.Set(float64(goState.WebhookWorkers))
	m.serversTotal.Set(float64(goState.ServersCount))

	if !m.serverStarted {
		m.serverStarted = true

		go func() {

			handler := promhttp.HandlerFor(m.registry, promhttp.HandlerOpts{})

			http.Handle("/", handler)
			http.Handle("/metrics", handler)

			m.logger.Info("Starting metrics server on :9667")

			if err := http.ListenAndServe(":9667", nil); err != nil {
				m.logger.Error("Failed to start metrics server", "error", err)
			}

		}()

		go func() {
			<-m.ctx.Done()
			m.logger.Info("Shutting down metrics server")
		}()

		go func() {

			for {
				select {
				case <-m.ctx.Done():
					return
				default:
					m.updateGlobalMetrics()
					time.Sleep(10 * time.Second)
				}
			}

		}()

	}
}

func (m *Metrics) updateGlobalMetrics() {

	conn, err := NewDbConn(LoadDBConfig())

	m.logger.Info("Updating global metrics...")

	if err != nil {
		m.logger.Error("Failed to connect to PostgreSQL to update metrics", "error", err)
		return
	}

	defer conn.Close()

	// connections
	var connections int
	err = conn.QueryRow("SELECT count(*) FROM pg_stat_activity").Scan(&connections)
	if err != nil {
		m.logger.Error("Failed to get PostgreSQL connections", "error", err)
		return
	}
	m.pgsqlConnections.Set(float64(connections))

	// max connections
	var maxConnections int
	err = conn.QueryRow("SELECT setting::int FROM pg_settings WHERE name = 'max_connections'").Scan(&maxConnections)
	if err != nil {
		m.logger.Error("Failed to get PostgreSQL max connections", "error", err)
		return
	}
	m.pgsqlMaxConnections.Set(float64(maxConnections))

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
		m.logger.Error("Failed to get email queue size", "error", err)
		return
	}

	for rows.Next() {
		var count int
		var queueName string

		if err := rows.Scan(&count, &queueName); err != nil {
			m.logger.Error("Failed to scan queue size", "error", err)
			continue
		}

		m.emailQueueSize.WithLabelValues(queueName).Set(float64(count))
	}

	if err := rows.Close(); err != nil {
		m.logger.Error("Failed to close rows", "error", err)
		return
	}

}
