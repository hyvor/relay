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
}

func NewEmailWorkersPool(
	ctx context.Context,
	logger *slog.Logger,
	metrics *Metrics,
) *EmailWorkersPool {
	pool := &EmailWorkersPool{
		ctx:     ctx,
		logger:  logger.With("component", "email_workers_pool"),
		metrics: metrics,
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
		for j := range workersPerIp {
			pool.wg.Add(1)
			worker := NewEmailWorker(
				ctx,
				i+j,
				&pool.wg,
				LoadDBConfig(),
				pool.logger,
				pool.metrics,
				ip,
				instanceDomain,
			)
			go worker.Start()
		}
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

type EmailWorker struct {
	ctx            context.Context
	id             int
	wg             *sync.WaitGroup
	dbConfig       *DBConfig
	logger         *slog.Logger
	metrics        *Metrics
	ip             GoStateIp
	instanceDomain string
}

func NewEmailWorker(
	ctx context.Context,
	id int,
	wg *sync.WaitGroup,
	dbConfig *DBConfig,
	logger *slog.Logger,
	metrics *Metrics,
	ip GoStateIp,
	instanceDomain string,
) *EmailWorker {
	return &EmailWorker{
		ctx:            ctx,
		id:             id,
		wg:             wg,
		dbConfig:       dbConfig,
		logger:         logger.With("worker_id", id, "ip", ip.Ip),
		metrics:        metrics,
		ip:             ip,
		instanceDomain: instanceDomain,
	}
}

func (worker *EmailWorker) Start() {

	defer worker.wg.Done()

	conn, err := NewRetryingDbConn(
		worker.ctx,
		worker.dbConfig,
		worker.logger,
	)
	if err != nil {
		return
	}
	defer conn.Close()

	for {

		select {
		case <-worker.ctx.Done():
			worker.logger.Info("Email worker stopped by context cancellation")
			return
		default:
			worker.ProcessSend(conn)
		}

	}

}

// This tries to handle one send within a transaction.
// The select statement uses FOR UPDATE SKIP LOCKED,
// which skips locked rows in other queries until the current transaction is committed or rolled back.
func (worker *EmailWorker) ProcessSend(conn *sql.DB) {

	sendTx, err := NewSendTransaction(worker.ctx, conn)

	if err != nil {
		worker.logger.Error(
			"Email worker failed to create a new send transaction",
			"error", err,
		)
		time.Sleep(1 * time.Second)
		return
	}

	send, recipients, err := sendTx.FetchSends(worker.ip.QueueId)

	if err != nil {

		if errors.Is(err, sql.ErrNoRows) {
			time.Sleep(1 * time.Second)
			sendTx.Rollback()
			return
		}

		worker.logger.Error(
			"Email worker failed to fetch a send",
			"error", err,
		)
		time.Sleep(1 * time.Second)
		sendTx.Rollback()
		return
	}

	recipientsByDomain := getRecipientsGroupedByDomain(recipients)

	var sendAttemptIds []int // TODO: this

	domainsCount := len(recipientsByDomain)
	domainWg := sync.WaitGroup{}
	domainWg.Add(domainsCount)
	domainQueryMutex := &sync.Mutex{}

	for domain, rcpts := range recipientsByDomain {
		go worker.AttemptSendToDomain(
			&domainWg,
			domainQueryMutex,
			send,
			domain,
			rcpts,
			sendTx,
		)
	}

	domainWg.Wait()

	if err := sendTx.FinalizeSend(send); err != nil {
		worker.logger.Error("Email worker failed to finalize send: "+err.Error(), "send_id", send.Id)
		sendTx.Rollback()
		return
	}

	commitErr := sendTx.Commit()

	if commitErr != nil {
		worker.logger.Error("Email worker failed to commit batch: "+commitErr.Error(), "send_id", send.Id)
		sendTx.Rollback()
		return
	}

	go notifySendAttemptsToSymfony(worker.ctx, sendAttemptIds, worker.logger)

	time.Sleep(50 * time.Millisecond)

	//

}

func (worker *EmailWorker) AttemptSendToDomain(
	domainWg *sync.WaitGroup,
	domainQueryMutex *sync.Mutex,
	send *SendRow,
	domain string,
	recipients []*RecipientRow,
	sendTx *SendTransaction,
) (int, error) {

	defer domainWg.Done()

	worker.logger.Info(
		"Email worker processing send for domain",
		"send_id", send.Id,
		"domain", domain,
		"recipients", len(recipients),
	)

	result := sendEmail(
		send,
		recipients,
		domain,
		worker.instanceDomain,
		worker.ip.Id,
		worker.ip.Ip,
		worker.ip.Ptr,
	)

	// get the lock before calling the DB
	domainQueryMutex.Lock()
	defer domainQueryMutex.Unlock()
	sendAttemptId, err := sendTx.RecordAttempt(
		send,
		recipients,
		result,
	)

	if err != nil {
		worker.logger.Error(
			"Email worker failed to record send attempt",
			"send_id", send.Id,
			"domain", domain,
			"error", err,
		)
		return 0, err
	}

	updateEmailMetricsFromSendResult(worker.metrics, result)

	return sendAttemptId, nil

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
