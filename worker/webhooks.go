package main

import (
	"context"
	"fmt"
	"io"
	"log/slog"
	"net"
	"net/http"
	"strings"
	"sync"
	"syscall"
	"time"
)

type WebhookWorkersPool struct {
	ctx        context.Context
	mu         sync.Mutex
	wg         sync.WaitGroup
	cancelFunc context.CancelFunc
	logger     *slog.Logger
	metrics    *Metrics
	workerFunc func(
		ctx context.Context,
		id int,
		wg *sync.WaitGroup,
		config *DBConfig,
		logger *slog.Logger,
		metrics *Metrics,
	)
}

func NewWebhookWorkersPool(
	ctx context.Context,
	logger *slog.Logger,
	metrics *Metrics,
) *WebhookWorkersPool {
	pool := &WebhookWorkersPool{
		ctx:        ctx,
		logger:     logger.With("component", "webhook_workers_pool"),
		workerFunc: webhookWorker,
		metrics:    metrics,
	}

	go func() {
		<-ctx.Done()
		logger.Info("Stopping webhook workers pool")
		pool.StopWorkers()
	}()

	return pool
}

// Starts or restarts the email workers state.
func (pool *WebhookWorkersPool) Set(workers int) {

	pool.StopWorkers()

	pool.mu.Lock()
	defer pool.mu.Unlock()

	ctx, cancel := context.WithCancel(pool.ctx)
	pool.cancelFunc = cancel

	pool.logger.Info(
		"Starting webhook workers",
		"workers", workers,
	)

	for i := range workers {
		pool.wg.Add(1)
		go pool.workerFunc(
			ctx,
			i,
			&pool.wg,
			LoadDBConfig(),
			pool.logger,
			pool.metrics,
		)
	}

}

func (pool *WebhookWorkersPool) StopWorkers() {

	pool.mu.Lock()
	defer pool.mu.Unlock()

	if pool.cancelFunc != nil {
		pool.cancelFunc()
		pool.cancelFunc = nil
	}

	pool.wg.Wait()

}

func webhookWorker(
	ctx context.Context,
	id int,
	wg *sync.WaitGroup,
	dbConfig *DBConfig,
	logger *slog.Logger,
	metrics *Metrics,
) {
	defer wg.Done()

	logger.Debug("Webhook worker started", "id", id)

	conn, err := NewRetryingDbConn(ctx, dbConfig, logger)
	if err != nil {
		logger.Error(
			"Webhook worker failed to connect to database",
			"worker_id", id,
			"error", err,
		)
		return
	}
	defer conn.Close()

	for {
		select {
		case <-ctx.Done():
			logger.Info("Webhook worker stopped", "id", id)
			return
		default:
			batch, err := NewWebhooksBatch(ctx, conn)

			if err != nil {
				logger.Error(
					"Webhook worker failed to get new webhooks batch",
					"worker_id", id,
					"error", err,
				)
				time.Sleep(1 * time.Second)
				continue
			}

			deliveries, err := batch.FetchWebhooks()

			if err != nil {
				logger.Error(
					"Worker failed to fetch webhook deliveries",
					"worker_id", id,
					"error", err,
				)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			if len(deliveries) == 0 {
				logger.Debug(
					"Worker found no webhook deliveries",
					"worker_id", id,
				)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			logger.Debug(
				"Worker found webhook deliveries",
				"worker_id", id,
				"count", len(deliveries),
			)

			var wg sync.WaitGroup
			wg.Add(len(deliveries))

			for _, delivery := range deliveries {

				go func(delivery WebhookDelivery) {
					defer wg.Done()

					logger.Info(
						"Worker processing webhook delivery",
						"worker_id", id,
						"webhook_delivery_id", delivery.Id,
					)

					result := sendWebhook(&delivery)

					err := batch.FinalizeWebhookByResult(&delivery, result)

					if err != nil {
						logger.Error(
							"Worker failed to finalize webhook delivery",
							"worker_id", id,
							"webhook_delivery_id", delivery.Id,
							"error", err,
						)
					}

					metrics.webhookDeliveriesTotal.WithLabelValues(result.MetricStatus()).Inc()

					logger.Debug(
						"Worker finalized webhook delivery",
						"worker_id", id,
						"webhook_delivery_id", delivery.Id,
						"status", result.MetricStatus(),
						"response_status_code", result.ResponseStatusCode,
						"response_body", result.ResponseBody,
					)
				}(delivery)

			}

			wg.Wait()

			batch.Commit()
			time.Sleep(1 * time.Second)

		}
	}
}

type WebhookResult struct {
	Success            bool
	ResponseBody       string // upto 1024 bytes
	ResponseStatusCode int
	NewTryCount        int
}

func (wr WebhookResult) MetricStatus() string {
	if wr.Success {
		return "success"
	} else if wr.NewTryCount >= WEBHOOKS_MAX_RETRIES {
		return "failed"
	}
	return "deferred"
}

// for testing
var blockInternalWebhookIPs = true

// these ranges are not covered by the standard net.IP helpers
var extraBlockedCidrs = mustParseCidrs(
	"0.0.0.0/8",     // "this" network
	"100.64.0.0/10", // CGNAT
	"192.0.0.0/24",  // IETF protocol assignments
	"198.18.0.0/15", // benchmarking
	"240.0.0.0/4",   // reserved
	"64:ff9b::/96",  // NAT64
)

func mustParseCidrs(cidrs ...string) []*net.IPNet {
	nets := make([]*net.IPNet, 0, len(cidrs))
	for _, c := range cidrs {
		_, n, err := net.ParseCIDR(c)
		if err != nil {
			panic(err)
		}
		nets = append(nets, n)
	}
	return nets
}

func isInternalIP(ip net.IP) bool {
	if v4 := ip.To4(); v4 != nil {
		ip = v4
	}
	if ip.IsUnspecified() || ip.IsLoopback() || ip.IsPrivate() ||
		ip.IsLinkLocalUnicast() || ip.IsLinkLocalMulticast() ||
		ip.IsInterfaceLocalMulticast() || ip.IsMulticast() {
		return true
	}
	for _, n := range extraBlockedCidrs {
		if n.Contains(ip) {
			return true
		}
	}
	return false
}

func webhookDialControl(network, address string, _ syscall.RawConn) error {
	if !blockInternalWebhookIPs {
		return nil
	}
	host, _, err := net.SplitHostPort(address)
	if err != nil {
		return err
	}
	ip := net.ParseIP(host)
	if ip == nil || isInternalIP(ip) {
		return fmt.Errorf("destination %s is not allowed", host)
	}
	return nil
}

var httpClient = &http.Client{
	Timeout: 30 * time.Second,
	Transport: &http.Transport{
		Proxy: nil, // a proxy would bypass the dial check
		DialContext: (&net.Dialer{
			Timeout: 10 * time.Second,
			Control: webhookDialControl,
		}).DialContext,
	},
	CheckRedirect: func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	},
}

func sendWebhook(delivery *WebhookDelivery) *WebhookResult {

	result := &WebhookResult{
		Success:     false,
		NewTryCount: delivery.TryCount + 1,
	}

	req, err := http.NewRequest("POST", delivery.Url, strings.NewReader(delivery.RequestBody))
	if err != nil {
		result.ResponseBody = "Request creation error: " + err.Error()
		return result
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Signature", delivery.Signature)

	resp, err := httpClient.Do(req)

	if err != nil {
		result.ResponseBody = "Network error: " + err.Error()
		return result
	}

	defer resp.Body.Close()

	limitReader := io.LimitReader(resp.Body, 1024)
	responseBody, _ := io.ReadAll(limitReader)
	result.ResponseBody = string(responseBody)
	result.ResponseStatusCode = resp.StatusCode

	if resp.StatusCode >= 200 && resp.StatusCode < 300 {
		result.Success = true
	}

	return result

}
