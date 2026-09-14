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
	cache      *SharedCache
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

		pool.mu.Lock()
		defer pool.mu.Unlock()
		pool.stopWorkersLocked()
	}()

	return pool
}

// Starts or restarts the email workers state.
func (pool *EmailWorkersPool) Set(
	ips []GoStateIp,
	workersPerIp int,
	instanceDomain string,
) {
	pool.mu.Lock()
	defer pool.mu.Unlock()

	pool.stopWorkersLocked()

	db, err := sql.Open("postgres", LoadDBConfig().DSN())
	if err != nil {
		pool.logger.Error("Failed to open the shared cache database handle", "error", err)
	}
	pool.cache = NewSharedCache(db)

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
				pool.cache,
			)
			go worker.Start()
		}
	}

}

// stopWorkersLocked requires pool.mu to be held.
func (pool *EmailWorkersPool) stopWorkersLocked() {
	if pool.cancelFunc != nil {
		pool.cancelFunc()
		pool.cancelFunc = nil
	}

	pool.wg.Wait()

	if pool.cache != nil {
		pool.cache.Close()
		pool.cache = nil
	}
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
	contentStore   SendContentStore
	cache          *SharedCache

	// mocks
	ProcessSendFunc         func(conn *sql.DB) error
	FetchContentFunc        func(uuid string) (string, error)
	AttemptSendToDomainFunc func(
		domainWg *sync.WaitGroup,
		domainQueryMutex *sync.Mutex,
		attemptCh chan<- AttemptData,
		send *SendRow,
		domain string,
		recipients []*RecipientRow,
		sendTx *SendTransaction,
	)
	SendEmailContextFunc func(context.Context, *SendRow, []*RecipientRow, string, string, int, string, string) *SendResult
}

var NewEmailWorker = newEmailWorker

func newEmailWorker(
	ctx context.Context,
	id int,
	wg *sync.WaitGroup,
	dbConfig *DBConfig,
	logger *slog.Logger,
	metrics *Metrics,
	ip GoStateIp,
	instanceDomain string,
	cache *SharedCache,
) *EmailWorker {
	contentStore, err := NewSendContentStore()
	if err != nil {
		logger.Error(
			"Failed to initialize send content store",
			"error", err,
		)
	}

	worker := &EmailWorker{
		ctx:            ctx,
		id:             id,
		wg:             wg,
		dbConfig:       dbConfig,
		logger:         logger.With("worker_id", id, "ip", ip.Ip),
		metrics:        metrics,
		ip:             ip,
		instanceDomain: instanceDomain,
		contentStore:   contentStore,
		cache:          cache,
	}

	worker.FetchContentFunc = worker.fetchContent
	worker.ProcessSendFunc = worker.processSend
	worker.AttemptSendToDomainFunc = worker.attemptSendToDomain
	worker.SendEmailContextFunc = func(
		ctx context.Context,
		send *SendRow,
		recipients []*RecipientRow,
		rcptDomain, instanceDomain string,
		ipId int,
		ip, ptr string,
	) *SendResult {
		return sendEmailHandlerContext(ctx, worker.cache, send, recipients, rcptDomain, instanceDomain, ipId, ip, ptr)
	}

	return worker
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
			worker.ProcessSendFunc(conn)
		}

	}

}

type AttemptData struct {
	result        *SendResult
	SendAttemptId int
	Error         error
}

// gets the raw MIME message from local API
func (worker *EmailWorker) fetchContent(uuid string) (string, error) {
	if worker.contentStore == nil {
		return "", errors.New("send content store is not initialized")
	}

	return worker.contentStore.GetRaw(worker.ctx, uuid)
}

// This tries to handle one send within a transaction.
// The select statement uses FOR UPDATE SKIP LOCKED,
// which skips locked rows in other queries until the current transaction is committed or rolled back.
func (worker *EmailWorker) processSend(conn *sql.DB) error {

	sendTx, err := NewSendTransaction(worker.ctx, conn)

	if err != nil {
		worker.logger.Error(
			"Email worker failed to create a new send transaction",
			"error", err,
		)
		time.Sleep(1 * time.Second)
		return err
	}

	send, recipients, err := sendTx.FetchSend(worker.ip.QueueId)

	if err != nil {

		if errors.Is(err, sql.ErrNoRows) {
			worker.logger.Debug(
				"Email worker found no sends to process. Retrying in 1 second",
				"queue_id", worker.ip.QueueId,
			)
			time.Sleep(1 * time.Second)
			sendTx.Rollback()
			return err
		}

		worker.logger.Error(
			"Email worker failed to fetch a send",
			"error", err,
		)
		time.Sleep(1 * time.Second)
		sendTx.Rollback()
		return err
	}

	recipientsByDomain := getRecipientsGroupedByDomain(recipients)

	rawEmail, err := worker.FetchContentFunc(send.Uuid)
	if err != nil {
		worker.logger.Error(
			"Email worker failed to fetch send content from storage",
			"error", err,
			"send_uuid", send.Uuid,
		)
		time.Sleep(1 * time.Second)
		sendTx.Rollback()
		return err
	}

	send.RawEmail = rawEmail

	var sendAttemptIds []int
	attemptCh := make(chan AttemptData, len(recipients))

	domainsCount := len(recipientsByDomain)
	domainWg := sync.WaitGroup{}
	domainWg.Add(domainsCount)
	domainQueryMutex := &sync.Mutex{}

	for domain, rcpts := range recipientsByDomain {
		go worker.AttemptSendToDomainFunc(
			&domainWg,
			domainQueryMutex,
			attemptCh,
			send,
			domain,
			rcpts,
			sendTx,
		)
	}

	go func() {
		domainWg.Wait()
		close(attemptCh)
	}()

	// 0 means not requeued the send again
	// otherwise it is set to the new try count for requeuing
	requeingTryCount := 0

	var attemptErr error
	for attempt := range attemptCh {
		if attempt.Error != nil {
			if attemptErr == nil {
				attemptErr = attempt.Error
			}
			continue
		} else {
			sendAttemptIds = append(sendAttemptIds, attempt.SendAttemptId)

			var hasDeferred = false
			for _, rcptResult := range attempt.result.RcptResults {
				code := rcptResult.ToRecipientStatus()
				if code == RecipientStatusDeferred {
					hasDeferred = true
					break
				}
			}

			if hasDeferred {
				requeingTryCount = attempt.result.NewTryCount
			}
		}
	}
	if attemptErr != nil {
		sendTx.Rollback()
		return attemptErr
	}

	if requeingTryCount > 0 {
		err = sendTx.RequeueSend(send.Id, requeingTryCount)
	} else {
		err = sendTx.MarkSendAsDone(send.Id)
	}

	if err != nil {
		worker.logger.Error("Email worker failed to finalize send: "+err.Error(), "send_id", send.Id)
		sendTx.Rollback()
		return err
	}

	commitErr := sendTx.Commit()

	if commitErr != nil {
		worker.logger.Error("Email worker failed to commit batch: "+commitErr.Error(), "send_id", send.Id)
		sendTx.Rollback()
		return commitErr
	}

	go notifySendAttemptsToSymfony(worker.ctx, sendAttemptIds, worker.logger)

	time.Sleep(50 * time.Millisecond)

	return nil

}

func (worker *EmailWorker) attemptSendToDomain(
	domainWg *sync.WaitGroup,
	domainQueryMutex *sync.Mutex,
	attemptCh chan<- AttemptData,
	send *SendRow,
	domain string,
	recipients []*RecipientRow,
	sendTx *SendTransaction,
) {

	defer domainWg.Done()

	worker.logger.Info(
		"Email worker processing send for domain",
		"send_id", send.Id,
		"domain", domain,
		"recipients", len(recipients),
	)

	result := worker.SendEmailContextFunc(worker.ctx, send, recipients, domain, worker.instanceDomain, worker.ip.Id, worker.ip.Ip, worker.ip.Ptr)

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

		attemptCh <- AttemptData{
			result:        nil,
			SendAttemptId: 0,
			Error:         err,
		}

		return
	}

	updateEmailMetricsFromSendResult(worker.metrics, result)

	attemptCh <- AttemptData{
		result:        result,
		SendAttemptId: sendAttemptId,
		Error:         nil,
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
		sendResultToAttemptStatus(sendResult),
	).Inc()

	metrics.emailDeliveryDurationSeconds.WithLabelValues(
		sendResult.QueueName,
		sendResult.SentFromIp,
	).Observe(sendResult.Duration.Seconds())

}
