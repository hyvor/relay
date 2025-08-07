package main

import (
	"context"
	"io"
	"log/slog"
	"net/http"
	"strings"
	"sync"
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
	logger.Info("Webhook worker started", "id", id)

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

var httpClient = &http.Client{}

func sendWebhook(delivery *WebhookDelivery) *WebhookResult {

	result := &WebhookResult{
		Success:     false,
		NewTryCount: delivery.TryCount + 1,
	}

	resp, err := httpClient.Post(delivery.Url, "application/json", strings.NewReader(delivery.RequestBody))

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
