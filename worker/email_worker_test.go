package main

import (
	"bytes"
	"context"
	"database/sql"
	"errors"
	"io"
	"log/slog"
	"sync"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestNewEmailWorkersPool(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, nil))
	pool := NewEmailWorkersPool(ctx, logger, newMetrics())

	assert.NotNil(t, pool)
	assert.Equal(t, ctx, pool.ctx)
	assert.Nil(t, pool.cancelFunc)

	cancel()
	time.Sleep(10 * time.Millisecond)

	assert.Contains(t, buf.String(), "Stopping email workers pool")
}

func TestEmailWorkersPoolSet(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())

	numWorkersCreated := 0

	NewEmailWorker = func(
		ctx context.Context,
		id int,
		wg *sync.WaitGroup,
		dbConfig *DBConfig,
		logger *slog.Logger,
		metrics *Metrics,
		ip GoStateIp,
		instanceDomain string,
	) *EmailWorker {
		numWorkersCreated++

		return newEmailWorker(ctx, id, wg, dbConfig, logger, metrics, ip, instanceDomain)
	}

	pool := &EmailWorkersPool{
		ctx:        ctx,
		cancelFunc: cancel,
		logger:     slogDiscard(),
	}

	pool.Set([]GoStateIp{
		{Ip: "1.1.1.1", QueueId: 1, QueueName: "transactional"},
		{Ip: "2.2.2.2", QueueId: 2, QueueName: "distributional"},
	}, 2, "relay.hyvor.com")

	assert.Equal(t, 4, numWorkersCreated)

}

func TestEmailWorkersPoolStopWorkers(t *testing.T) {

	canceled := false
	cancelFunc := func() {
		canceled = true
	}

	pool := &EmailWorkersPool{
		ctx:        context.Background(),
		cancelFunc: cancelFunc,
	}

	pool.StopWorkers()

	time.Sleep(10 * time.Millisecond)

	assert.True(t, canceled)
	assert.Nil(t, pool.cancelFunc)

}

// worker testing

func TestEmailWorker_DatabaseConnectionFailure(t *testing.T) {

	ctx, cancel := context.WithCancel(context.Background())

	var wg sync.WaitGroup
	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, nil))

	dbConfig := &DBConfig{
		Host:     "localhost",
		Port:     "5432",
		User:     "test",
		Password: "test",
		DBName:   "test",
		SSLMode:  "disable",
	}

	ip := GoStateIp{
		Ip:        "1.1.1.1",
		QueueId:   1,
		QueueName: "test",
	}

	originalNewDbConn := NewDbConn
	NewDbConn = func(config *DBConfig) (*sql.DB, error) {
		return nil, errors.New("connection failed")
	}
	defer func() { NewDbConn = originalNewDbConn }()

	wg.Add(2)

	emailWorker := NewEmailWorker(
		ctx,
		1,
		&wg,
		dbConfig,
		logger,
		newMetrics(),
		ip,
		"relay.hyvor.com",
	)
	go emailWorker.Start()
	go func() {
		defer wg.Done()
		time.Sleep(40 * time.Millisecond) // Simulate some work
		cancel()                          // Cancel the context to stop the worker
	}()
	wg.Wait()

	assert.Contains(t, buf.String(), "Failed to connect to database, retrying")
	assert.Contains(t, buf.String(), "connection failed")
}

func TestEmailWorker_CallsProcessSend(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())

	calledTimes := 0

	workerWg := &sync.WaitGroup{}
	workerWg.Add(1)
	emailWorker := &EmailWorker{
		wg:       workerWg,
		ctx:      ctx,
		dbConfig: getTestDbConfig(),
		ProcessSendFunc: func(conn *sql.DB) error {
			calledTimes++
			cancel()
			return nil
		},
		logger: slogDiscard(),
	}

	go emailWorker.Start()
	workerWg.Wait()

	assert.Equal(t, 1, calledTimes)
}

func TestEmailWorker_ProcessSend_RollsbackWhenNoRowsFound(t *testing.T) {

	var buf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&buf, &slog.HandlerOptions{
		Level: slog.LevelDebug,
	}))
	ctx, cancel := context.WithCancel(context.Background())
	wg := &sync.WaitGroup{}
	emailWorker := &EmailWorker{
		ctx:    ctx,
		logger: logger,
	}

	conn, err := createNewTestDbConn()
	assert.NoError(t, err)

	wg.Add(2)
	go func() {
		defer wg.Done()
		emailWorker.processSend(conn)
	}()

	go func() {
		defer wg.Done()
		time.Sleep(100 * time.Millisecond)
		cancel()
	}()

	wg.Wait()

	assert.Contains(t, buf.String(), "Email worker found no sends to process. Retrying in 1 second")

}

func TestEmailWorker_ProcessSend(t *testing.T) {

	truncateTestDb()

	factory, err := NewTestFactory()
	assert.NoError(t, err)

	send, err := factory.Send(&FactorySend{
		Queued:    true,
		SendAfter: time.Now().Add(-10 * time.Hour),
	})
	assert.NoError(t, err)

	rcpt1Id, err := factory.SendRecipient(send, &FactorySendRecipient{
		Address: "supun@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	rcpt2Id, err := factory.SendRecipient(send, &FactorySendRecipient{
		Address: "ishini@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	rcpt3Id, err := factory.SendRecipient(send, &FactorySendRecipient{
		Address: "nadil@gmail.com",
		Type:    "cc",
		Status:  "queued",
	})
	assert.NoError(t, err)

	calledDomains := make(map[string][]*RecipientRow)
	calledDomainsMutex := &sync.Mutex{}

	worker := &EmailWorker{
		ctx:    context.Background(),
		logger: slog.New(slog.NewTextHandler(io.Discard, nil)),
		ip: GoStateIp{
			QueueId: send.QueueId,
		},
		AttemptSendToDomainFunc: func(
			domainWg *sync.WaitGroup,
			domainQueryMutex *sync.Mutex,
			attemptCh chan<- AttemptData,
			send *SendRow,
			domain string,
			recipients []*RecipientRow,
			sendTx *SendTransaction,
		) {
			defer domainWg.Done()
			calledDomainsMutex.Lock()
			defer calledDomainsMutex.Unlock()
			calledDomains[domain] = recipients

			if domain == "hyvor.com" {
				attemptCh <- AttemptData{
					SendAttemptId: 1,
					Error:         nil,
					result: &SendResult{
						RcptResults: []*RcptResult{
							{
								RecipientId: rcpt1Id,
								Code:        250,
								EnhancedCode: [3]int{2, 0, 0},
								Message:     "OK",
							},
							{
								RecipientId: rcpt2Id,
								Code:        250,
								EnhancedCode: [3]int{2, 0, 0},
								Message:     "OK",
							},
						},
					},
				}
			} else if domain == "gmail.com" {
				attemptCh <- AttemptData{
					SendAttemptId: 2,
					Error:         nil,
					result: &SendResult{
						RcptResults: []*RcptResult{
							{
								RecipientId: rcpt3Id,
								Code:        250,
								EnhancedCode: [3]int{2, 0, 0},
								Message: "OK",
							},
						},
					},
				}
			}
		},
	}

	var localApiMethod string
	var localApiEndpoint string
	var localApiBody interface{}

	CallLocalApi = func(ctx context.Context, method, endpoint string, body, responseJsonObject interface{}) error {
		localApiMethod = method
		localApiEndpoint = endpoint
		localApiBody = body

		return nil
	}

	conn, err := createNewTestDbConn()
	assert.NoError(t, err)

	err = worker.processSend(conn)
	assert.NoError(t, err)

	assert.Equal(t, 2, len(calledDomains))

	hyvorRecipients, ok := calledDomains["hyvor.com"]
	assert.True(t, ok)
	assert.Equal(t, 2, len(hyvorRecipients))
	assert.Equal(t, "supun@hyvor.com", hyvorRecipients[0].Address)
	assert.Equal(t, "ishini@hyvor.com", hyvorRecipients[1].Address)

	gmailRecipients, ok := calledDomains["gmail.com"]
	assert.True(t, ok)
	assert.Equal(t, 1, len(gmailRecipients))
	assert.Equal(t, "nadil@gmail.com", gmailRecipients[0].Address)

	assert.Equal(t, "POST", localApiMethod)
	assert.Equal(t, "/send-attempts/done", localApiEndpoint)
	bodyMap, ok := localApiBody.(map[string]interface{})
	assert.True(t, ok)
	sendAttemptIds, ok := bodyMap["send_attempt_ids"].([]int)
	assert.True(t, ok)
	assert.Equal(t, 2, len(sendAttemptIds))
	assert.Contains(t, sendAttemptIds, 1)
	assert.Contains(t, sendAttemptIds, 2)

	updatedSend, err := factory.GetSendById(send.Id)
	assert.NoError(t, err)
	assert.False(t, updatedSend.Queued)

}

func TestEmailWorker_ProcessSend_Requeuing(t *testing.T) {

	truncateTestDb()

	factory, err := NewTestFactory()
	assert.NoError(t, err)

	send, err := factory.Send(&FactorySend{
		Queued:    true,
		SendAfter: time.Now().Add(-10 * time.Hour),
	})
	assert.NoError(t, err)

	rcptId, err := factory.SendRecipient(send, &FactorySendRecipient{
		Address: "supun@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	worker := &EmailWorker{
		ctx:    context.Background(),
		logger: slogDiscard(),
		ip: GoStateIp{
			QueueId: send.QueueId,
		},
		AttemptSendToDomainFunc: func(
			domainWg *sync.WaitGroup,
			domainQueryMutex *sync.Mutex,
			attemptCh chan<- AttemptData,
			send *SendRow,
			domain string,
			recipients []*RecipientRow,
			sendTx *SendTransaction,
		) {
			defer domainWg.Done()

			attemptCh <- AttemptData{
				SendAttemptId: 1,
				result: &SendResult{
					RcptResults: []*RcptResult{
						{
							RecipientId: rcptId,
							Code:        450,
							EnhancedCode: [3]int{4, 2, 0},
							Message:     "Try again later",
						},
					},
					NewTryCount: 1,
				},
			}

		},
	}

	conn, err := createNewTestDbConn()
	assert.NoError(t, err)

	err = worker.processSend(conn)
	assert.NoError(t, err)

	updatedSend, err := factory.GetSendById(send.Id)
	assert.NoError(t, err)
	assert.True(t, updatedSend.Queued)
	assert.True(t, updatedSend.SendAfter.After(time.Now()))

}

// attemptSendToDomain

func TestEmailWorker_AttemptSendToDomain(t *testing.T) {

	truncateTestDb()

	factory, err := NewTestFactory()
	assert.NoError(t, err)

	send, err := factory.Send(&FactorySend{
		Queued:    true,
		SendAfter: time.Now().Add(-10 * time.Hour),
	})
	assert.NoError(t, err)

	recipientId, err := factory.SendRecipient(send, &FactorySendRecipient{
		Address: "supun@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	wg := &sync.WaitGroup{}
	mx := &sync.Mutex{}
	attemptCh := make(chan AttemptData, 1)
	defer close(attemptCh)

	sendRow := &SendRow{
		Id:   send.Id,
		Uuid: send.Uuid,
		From: send.FromAddress,
	}
	domain := "hyvor.com"
	recipients := []*RecipientRow{
		{
			Id:       recipientId,
			Type:     "to",
			Address:  "supun@hyvor.com",
			TryCount: 0,
		},
	}
	sendTx, err := NewSendTransaction(context.Background(), factory.conn)
	assert.NoError(t, err)

	ipAddressId, err := factory.IpAddress()
	assert.NoError(t, err)

	worker := &EmailWorker{
		ctx:    context.Background(),
		logger: slogDiscard(),
		ip: GoStateIp{
			Id:      ipAddressId,
			QueueId: send.QueueId,
		},
		metrics: newMetrics(),
	}

	sendEmail = func(
		send *SendRow,
		recipients []*RecipientRow,
		rcptDomain string,
		instanceDomain string,
		ipId int,
		ip string,
		ptr string,
	) *SendResult {
		return &SendResult{
			SentFromIpId:    ipId,
			NewTryCount:     1,
			Domain:          "hyvor.com",
			ResolvedMxHosts: []string{"mx1.hyvor.com", "mx2.hyvor.com"},
			RcptResults: []*RcptResult{
				{
					RecipientId: recipientId,
					Code:        250,
					EnhancedCode: [3]int{2, 0, 0},
					Message:     "OK",
				},
			},
		}
	}

	chData := make([]AttemptData, 0)
	go func() {
		for data := range attemptCh {
			chData = append(chData, data)
		}
	}()

	wg.Add(1)
	worker.attemptSendToDomain(
		wg,
		mx,
		attemptCh,
		sendRow,
		domain,
		recipients,
		sendTx,
	)
	wg.Wait()
	time.Sleep(20 * time.Millisecond)

	assert.Equal(t, 1, len(chData))
	data := chData[0]
	assert.NotZero(t, data.SendAttemptId)
	assert.NoError(t, data.Error)

	sendTx.Commit()

	updatedSendAttempt, err := factory.GetSendAttemptById(data.SendAttemptId)
	assert.NoError(t, err)
	assert.Equal(t, "accepted", updatedSendAttempt.Status)
	assert.Equal(t, "hyvor.com", updatedSendAttempt.Domain)
	assert.Equal(t, 1, updatedSendAttempt.TryCount)
	assert.Equal(t, `["mx1.hyvor.com", "mx2.hyvor.com"]`, updatedSendAttempt.ResolvedMx)
	assert.Equal(t, `["mx1.hyvor.com", "mx2.hyvor.com"]`, updatedSendAttempt.ResolvedMx)

	assert.Equal(t, 1, len(updatedSendAttempt.Recipients))
	assert.Equal(t, 250, updatedSendAttempt.Recipients[0].SmtpCode)
	assert.Equal(t, "2.0.0", updatedSendAttempt.Recipients[0].SmtpEnhancedCode)
	assert.Equal(t, "OK", updatedSendAttempt.Recipients[0].SmtpMessage)

	updatedRecipient, err := factory.GetSendRecipientById(recipientId)
	assert.NoError(t, err)
	assert.Equal(t, "accepted", updatedRecipient.Status)
	assert.Equal(t, 1, updatedRecipient.TryCount)

}

func TestEmailWorker_AttemptSendToDomain_BounceReason(t *testing.T) {

	truncateTestDb()

	factory, err := NewTestFactory()
	assert.NoError(t, err)

	send, err := factory.Send(&FactorySend{
		Queued:    true,
		SendAfter: time.Now().Add(-10 * time.Hour),
	})
	assert.NoError(t, err)

	recipientId, err := factory.SendRecipient(send, &FactorySendRecipient{
		Address: "supun@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	wg := &sync.WaitGroup{}
	mx := &sync.Mutex{}
	attemptCh := make(chan AttemptData, 1)
	defer close(attemptCh)

	sendRow := &SendRow{
		Id:   send.Id,
		Uuid: send.Uuid,
		From: send.FromAddress,
	}
	domain := "hyvor.com"
	recipients := []*RecipientRow{
		{
			Id:       recipientId,
			Type:     "to",
			Address:  "supun@hyvor.com",
			TryCount: 0,
		},
	}
	sendTx, err := NewSendTransaction(context.Background(), factory.conn)
	assert.NoError(t, err)

	ipAddressId, err := factory.IpAddress()
	assert.NoError(t, err)

	worker := &EmailWorker{
		ctx:    context.Background(),
		logger: slogDiscard(),
		ip: GoStateIp{
			Id:      ipAddressId,
			QueueId: send.QueueId,
		},
		metrics: newMetrics(),
	}

	// Test recipient bounce (5.1.1)
	sendEmail = func(
		send *SendRow,
		recipients []*RecipientRow,
		rcptDomain string,
		instanceDomain string,
		ipId int,
		ip string,
		ptr string,
	) *SendResult {
		return &SendResult{
			SentFromIpId:    ipId,
			NewTryCount:     1,
			Domain:          "hyvor.com",
			ResolvedMxHosts: []string{"mx1.hyvor.com"},
			RcptResults: []*RcptResult{
				{
					RecipientId:  recipientId,
					Code:         550,
					EnhancedCode: [3]int{5, 1, 1},
					Message:      "User unknown",
					BounceReason: BounceReasonRecipient,
				},
			},
		}
	}

	wg.Add(1)
	worker.attemptSendToDomain(
		wg,
		mx,
		attemptCh,
		sendRow,
		domain,
		recipients,
		sendTx,
	)
	wg.Wait()

	chData := <-attemptCh
	sendTx.Commit()

	updatedSendAttempt, err := factory.GetSendAttemptById(chData.SendAttemptId)
	assert.NoError(t, err)
	assert.Equal(t, "bounced", updatedSendAttempt.Status)

	assert.Equal(t, 1, len(updatedSendAttempt.Recipients))
	assert.Equal(t, 550, updatedSendAttempt.Recipients[0].SmtpCode)
	assert.Equal(t, "recipient", updatedSendAttempt.Recipients[0].BounceReason)

	updatedRecipient, err := factory.GetSendRecipientById(recipientId)
	assert.NoError(t, err)
	assert.Equal(t, "bounced", updatedRecipient.Status)

	// Test infrastructure bounce (5.7.1)
	truncateTestDb()

	factory2, err := NewTestFactory()
	assert.NoError(t, err)

	send2, err := factory2.Send(&FactorySend{
		Queued:    true,
		SendAfter: time.Now().Add(-10 * time.Hour),
	})
	assert.NoError(t, err)

	recipientId2, err := factory2.SendRecipient(send2, &FactorySendRecipient{
		Address: "test@example.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	sendRow2 := &SendRow{
		Id:   send2.Id,
		Uuid: send2.Uuid,
		From: send2.FromAddress,
	}
	recipients2 := []*RecipientRow{
		{
			Id:       recipientId2,
			Type:     "to",
			Address:  "test@example.com",
			TryCount: 0,
		},
	}
	sendTx2, err := NewSendTransaction(context.Background(), factory2.conn)
	assert.NoError(t, err)

	ipAddressId2, err := factory2.IpAddress()
	assert.NoError(t, err)

	worker2 := &EmailWorker{
		ctx:    context.Background(),
		logger: slogDiscard(),
		ip: GoStateIp{
			Id:      ipAddressId2,
			QueueId: send2.QueueId,
		},
		metrics: newMetrics(),
	}

	sendEmail = func(
		send *SendRow,
		recipients []*RecipientRow,
		rcptDomain string,
		instanceDomain string,
		ipId int,
		ip string,
		ptr string,
	) *SendResult {
		return &SendResult{
			SentFromIpId:    ipId,
			NewTryCount:     1,
			Domain:          "example.com",
			ResolvedMxHosts: []string{"mx1.example.com"},
			RcptResults: []*RcptResult{
				{
					RecipientId:  recipientId2,
					Code:         550,
					EnhancedCode: [3]int{5, 7, 1},
					Message:      "Message rejected due to security policy",
					BounceReason: BounceReasonInfrastructure,
				},
			},
		}
	}

	attemptCh2 := make(chan AttemptData, 1)
	wg2 := &sync.WaitGroup{}
	wg2.Add(1)
	worker2.attemptSendToDomain(
		wg2,
		mx,
		attemptCh2,
		sendRow2,
		"example.com",
		recipients2,
		sendTx2,
	)
	wg2.Wait()

	chData2 := <-attemptCh2
	sendTx2.Commit()

	updatedSendAttempt2, err := factory2.GetSendAttemptById(chData2.SendAttemptId)
	assert.NoError(t, err)
	assert.Equal(t, "bounced", updatedSendAttempt2.Status)

	assert.Equal(t, 1, len(updatedSendAttempt2.Recipients))
	assert.Equal(t, 550, updatedSendAttempt2.Recipients[0].SmtpCode)
	assert.Equal(t, "infrastructure", updatedSendAttempt2.Recipients[0].BounceReason)

	updatedRecipient2, err := factory2.GetSendRecipientById(recipientId2)
	assert.NoError(t, err)
	assert.Equal(t, "bounced", updatedRecipient2.Status)
}
