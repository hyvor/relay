package main

import (
	"context"
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
	workerFunc func(
		ctx context.Context,
		id int,
		wg *sync.WaitGroup,
		config *DBConfig,
		logger *slog.Logger,
	)
}

func NewWebhookWorkersPool(
	ctx context.Context,
	logger *slog.Logger,
) *WebhookWorkersPool {
	pool := &WebhookWorkersPool{
		ctx:        ctx,
		logger:     logger,
		workerFunc: webhookWorker,
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
					"Webhook worker failed to create batch",
					"worker_id", id,
					"error", err,
				)
				time.Sleep(1 * time.Second)
				batch.Rollback()
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

					sendWebhook(&delivery)

					/* err := batch.FinalizeWebhookByResult(&send, result)

					if err != nil {
						logger.Error(
							"Worker failed to finalize webhook delivery",
							"worker_id", id,
							"webhook_id", send.Id,
							"error", err,
						)
					} */
				}(delivery)
			}

			wg.Wait()

			batch.Commit()
			time.Sleep(1 * time.Second)

		}
	}
}

type WebhookResult struct {
}

func sendWebhook(delivery *WebhookDelivery) *WebhookResult {

	// resp, err :=
	http.Post(delivery.Url, "application/json", strings.NewReader(delivery.RequestBody))

	//

	return &WebhookResult{}

}
