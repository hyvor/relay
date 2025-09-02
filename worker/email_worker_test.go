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

// func TestEmailWorkersPoolSet(t *testing.T) {

// 	canceled := false
// 	cancelFunc := func() {
// 		canceled = true
// 	}

// 	var called []int
// 	var mu sync.Mutex
// 	mockWorker := func(ctx context.Context, id int, wg *sync.WaitGroup, config *DBConfig, logger *slog.Logger, metrics *Metrics, ip GoStateIp, instanceDomain string) {
// 		defer wg.Done()
// 		mu.Lock()
// 		called = append(called, id)
// 		mu.Unlock()
// 	}

// 	pool := &EmailWorkersPool{
// 		ctx:        context.Background(),
// 		cancelFunc: cancelFunc,
// 		workerFunc: mockWorker,
// 		logger:     slog.New(slog.NewTextHandler(io.Discard, nil)),
// 	}

// 	pool.Set([]GoStateIp{
// 		{Ip: "1.1.1.1", QueueId: 1, QueueName: "transactional"},
// 		{Ip: "2.2.2.2", QueueId: 2, QueueName: "distributional"},
// 	}, 2, "relay.hyvor.com")

// 	time.Sleep(20 * time.Millisecond)

// 	assert.True(t, canceled)
// 	assert.Equal(t, 2, len(called))

// }

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

func TestEmailWorker_AttemptsToSendToGroupByDomain(t *testing.T) {

	truncateTestDb()

	factory, err := NewTestFactory()
	assert.NoError(t, err)

	send, err := factory.Send(&FactorySend{
		Queued:    true,
		SendAfter: time.Now().Add(-10 * time.Hour),
	})
	assert.NoError(t, err)

	err = factory.SendRecipient(send, &FactorySendRecipient{
		Address: "supun@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	err = factory.SendRecipient(send, &FactorySendRecipient{
		Address: "ishini@hyvor.com",
		Type:    "to",
		Status:  "queued",
	})
	assert.NoError(t, err)

	err = factory.SendRecipient(send, &FactorySendRecipient{
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
		},
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

	err = factory.SendRecipient(send, &FactorySendRecipient{
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
			Id:       1,
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
			SentFromIpId: ipId,
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

}
