package main

import (
	"context"
	"log/slog"
	"os"
	"sync"
	"time"
)

type EmailWorkersPool struct {
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
		ip GoStateIp,
		instanceDomain string,
	)
}

func NewEmailWorkersPool(
	ctx context.Context,
	logger *slog.Logger,
) *EmailWorkersPool {
	pool := &EmailWorkersPool{
		ctx:        ctx,
		logger:     logger,
		workerFunc: emailWorker,
	}

	go func() {
		<-ctx.Done()
		logger.Info("Stopping email workers pool")
		pool.StopWorkers()
	}()

	return pool
}

// Starts or restarts the email workers state.
func (pool *EmailWorkersPool) Set(
	ips []GoStateIp,
	workersPerIp int,
	instanceDomain string,
) {

	pool.StopWorkers()

	pool.mu.Lock()
	defer pool.mu.Unlock()

	ctx, cancel := context.WithCancel(pool.ctx)
	pool.cancelFunc = cancel

	pool.logger.Info(
		"Starting email workers",
		"total_ips", len(ips),
		"total_workers", len(ips)*workersPerIp,
	)

	for i, ip := range ips {
		pool.wg.Add(1)
		go pool.workerFunc(
			ctx,
			i,
			&pool.wg,
			LoadDBConfig(),
			pool.logger,
			ip,
			instanceDomain,
		)
	}

}

func (pool *EmailWorkersPool) StopWorkers() {

	pool.mu.Lock()
	defer pool.mu.Unlock()

	if pool.cancelFunc != nil {
		pool.cancelFunc()
		pool.cancelFunc = nil
	}

	pool.wg.Wait()

}

func emailWorker(
	ctx context.Context,
	id int,
	wg *sync.WaitGroup,
	dbConfig *DBConfig,
	logger *slog.Logger,
	ip GoStateIp,
	instanceDomain string,
) {
	defer wg.Done()

	conn, err := NewRetryingDbConn(ctx, dbConfig, logger)
	if err != nil {
		return
	}
	defer conn.Close()

	for {
		select {
		case <-ctx.Done():
			logger.Info(
				"Worker stopped by context cancellation",
				"id", id,
			)
			return

		default:

			batch, err := NewDbSendBatch(ctx, conn)

			if err != nil {
				logger.Error(
					"Worker failed to create batch",
					"worker_id", id,
					"error", err,
				)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			sends, err := batch.FetchSends(ip.QueueId)

			if err != nil {
				logger.Error(
					"Worker failed to fetch sends",
					"worker_id", id,
					"error", err,
				)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			var sendAttemptIds []int

			for _, send := range sends {
				logger.Info(
					"Worker processing send",
					"worker_id", id,
					"send_id", send.Id,
				)

				result := sendEmail(
					&send,
					instanceDomain,
					ip.Id,
					ip.Ip,
					ip.Ptr,
					os.Stdout,
				)

				sendAttemptId, err := batch.FinalizeSendBySendResult(&send, result)

				if err != nil {
					logger.Info(
						"Worker failed to finalize send",
						"worker_id", id,
						"send_id", send.Id,
						"error", err,
					)
					continue
				}

				sendAttemptIds = append(sendAttemptIds, sendAttemptId)
			}

			commitErr := batch.Commit()

			if commitErr != nil {
				logger.Error(
					"Worker failed to commit batch",
					"worker_id", id,
					"error", commitErr,
				)
			}

			go func(sendAttemptIds []int) {
				err := CallLocalApi(
					ctx,
					"POST",
					"/send-attempt/done",
					map[string]interface{}{
						"send_attempt_ids": sendAttemptIds,
					},
					nil,
				)
				if err != nil {
					logger.Error(
						"Worker failed to notify send attempt done via local API",
						"worker_id", id,
						"send_attempt_ids", sendAttemptIds,
						"error", err,
					)
				}
			}(sendAttemptIds)

			time.Sleep(1 * time.Second)

		}
	}
}
