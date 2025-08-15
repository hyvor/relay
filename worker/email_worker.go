package main

import (
	"context"
	"database/sql"
	"errors"
	"log/slog"
	"sync"
	"time"
)

type EmailWorkersPool struct {
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
		ip GoStateIp,
		instanceDomain string,
	)
}

func NewEmailWorkersPool(
	ctx context.Context,
	logger *slog.Logger,
	metrics *Metrics,
) *EmailWorkersPool {
	pool := &EmailWorkersPool{
		ctx:        ctx,
		logger:     logger.With("component", "email_workers_pool"),
		metrics:    metrics,
		workerFunc: emailWorker,
	}

	go func() {
		<-ctx.Done()
		pool.logger.Info("Stopping email workers pool")
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
			pool.metrics,
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
	metrics *Metrics,
	ip GoStateIp,
	instanceDomain string,
) {
	defer wg.Done()

	logger = logger.With(
		"worker_id", id,
		"ip", ip.Ip,
	)

	conn, err := NewRetryingDbConn(ctx, dbConfig, logger)
	if err != nil {
		return
	}
	defer conn.Close()

	for {
		select {
		case <-ctx.Done():
			logger.Info(
				"Email worker stopped by context cancellation",
				"id", id,
			)
			return

		default:

			sendTx, err := NewSendTransaction(ctx, conn)

			if err != nil {
				logger.Error(
					"Email worker failed to create a new send transaction",
					"error", err,
				)
				time.Sleep(1 * time.Second)
				continue
			}

			send, recipients, err := sendTx.FetchSends(ip.QueueId)

			if err != nil {

				if errors.Is(err, sql.ErrNoRows) {
					time.Sleep(250 * time.Millisecond)
					sendTx.Rollback()
					continue
				}

				logger.Error(
					"Email worker failed to fetch a send",
					"error", err,
				)
				time.Sleep(1 * time.Second)
				sendTx.Rollback()
				continue
			}

			recipientsByDomain := getRecipientsGroupedByDomain(recipients)
			var sendAttemptIds []int

			for domain, rcpts := range recipientsByDomain {

				logger.Info(
					"Email worker processing send for domain",
					"send_id", send.Id,
					"domain", domain,
				)

				result := sendEmail(
					send,
					rcpts,
					domain,
					instanceDomain,
					ip.Id,
					ip.Ip,
					ip.Ptr,
				)

				sendAttemptId, err := sendTx.RecordAttempt(
					send,
					rcpts,
					result,
				)

				if err != nil {
					logger.Info(
						"Email worker failed to finalize send",
						"send_id", send.Id,
						"domain", domain,
						"error", err,
					)
					continue
				}

				updateEmailMetricsFromSendResult(metrics, result)
				sendAttemptIds = append(sendAttemptIds, sendAttemptId)

			}

			if err := sendTx.FinalizeSend(send); err != nil {
				logger.Error("Email worker failed to finalize send",
					"send_id", send.Id,
					"error", err,
				)
			}

			commitErr := sendTx.Commit()

			if commitErr != nil {
				logger.Error("Email worker failed to commit batch",
					"send_id", send.Id,
					"error", commitErr,
				)
			}

			go notifySendAttemptsToSymfony(ctx, sendAttemptIds, logger)

			time.Sleep(50 * time.Millisecond)
		}
	}
}

func getRecipientsGroupedByDomain(rcpts []*RecipientRow) map[string][]*RecipientRow {
	recipientsByDomain := make(map[string][]*RecipientRow)

	for _, rcpt := range rcpts {
		domain := getDomainFromEmail(rcpt.Address)
		if _, exists := recipientsByDomain[domain]; !exists {
			recipientsByDomain[domain] = []*RecipientRow{}
		}
		recipientsByDomain[domain] = append(recipientsByDomain[domain], rcpt)
	}

	return recipientsByDomain
}

func notifySendAttemptsToSymfony(
	ctx context.Context,
	sendAttemptIds []int,
	logger *slog.Logger,
) {
	if len(sendAttemptIds) == 0 {
		return
	}

	err := CallLocalApi(
		ctx,
		"POST",
		"/send-attempts/done",
		map[string]interface{}{
			"send_attempt_ids": sendAttemptIds,
		},
		nil,
	)
	if err != nil {
		logger.Error(
			"Email worker failed to notify send attempt done via local API",
			"send_attempt_ids", sendAttemptIds,
			"error", err,
		)
	}
}

func updateEmailMetricsFromSendResult(
	metrics *Metrics,
	sendResult *SendResult,
) {

	metrics.emailSendAttemptsTotal.WithLabelValues(
		sendResult.QueueName,
		sendResult.SentFromIp,
		sendResult.ToStatus(),
	).Inc()

	metrics.emailDeliveryDurationSeconds.WithLabelValues(
		sendResult.QueueName,
		sendResult.SentFromIp,
	).Observe(sendResult.Duration.Seconds())

}
