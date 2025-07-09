package main

import (
	"context"
	"log/slog"
	"sync"
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
		instanceDomain string,
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
func (pool *WebhookWorkersPool) Set(workers int, instanceDomain string) {

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
			instanceDomain,
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
	instanceDomain string,
) {
	defer wg.Done()
	logger.Info("Webhook worker started", "id", id)

	conn, err := NewRetryingDbConn(ctx, dbConfig, logger)
	if err != nil {
		return
	}
	defer conn.Close()

	select {
	case <-ctx.Done():
		logger.Info("Webhook worker stopped", "id", id)
		return
	default:
		// Do the actual work here
	}
}
