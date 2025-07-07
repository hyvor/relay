package main

import (
	"context"
	"log"
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
	workerFunc func(ctx context.Context, id int, wg *sync.WaitGroup, config *DBConfig, ip GoStateIp)
}

func NewEmailWorkersPool(
	ctx context.Context,
	logger *slog.Logger,
) *EmailWorkersPool {
	pool := &EmailWorkersPool{
		ctx:        ctx,
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
) {

	pool.StopWorkers()

	pool.mu.Lock()
	defer pool.mu.Unlock()

	ctx, cancel := context.WithCancel(pool.ctx)
	pool.cancelFunc = cancel

	log.Printf("Starting %d email workers for %d IPs\n", len(ips)*workersPerIp, len(ips))

	for i, ip := range ips {
		pool.wg.Add(1)
		go pool.workerFunc(
			ctx,
			i,
			&pool.wg,
			LoadDBConfig(),
			ip,
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
	ip GoStateIp,
) {
	defer wg.Done()

	// TODO: implement reconnection logic
	conn, err := NewDbConn(dbConfig)

	if err != nil {
		log.Printf("Worker %d failed to connect to database: %v\n", id, err)
		return
	}

	defer conn.Close()

	for {
		select {
		case <-ctx.Done():
			log.Printf("Worker %d stopping\n", id)
			return

		default:

			batch, err := NewDbSendBatch(ctx, conn)

			if err != nil {
				log.Printf("Worker %d failed to create batch: %v\n", id, err)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			sends, err := batch.FetchSends(ip.QueueId)

			if err != nil {
				log.Printf("Worker %d failed to get send IDs: %v\n", id, err)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			for _, send := range sends {
				log.Printf("Worker %d processing send ID %d from %s to %s\n", id, send.Id, send.From, send.To)

				result := sendEmail(
					&send,
					ip,
					os.Stdout,
				)

				err := batch.FinalizeSendBySendResult(&send, result)

				if err != nil {
					log.Printf("Worker %d failed to finalize send ID %d: %v\n", id, send.Id, err)
					continue
				}
			}

			batch.Commit()
			time.Sleep(1 * time.Second)

		}
	}
}
