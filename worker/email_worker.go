package main

import (
	"context"
	"log"
	"os"
	"sync"
	"time"
)

type EmailWorkersPool struct {
	ctx        context.Context
	mu         sync.Mutex
	wg         sync.WaitGroup
	cancelFunc context.CancelFunc
}

func NewEmailWorkersPool(
	ctx context.Context,
) *EmailWorkersPool {
	pool := &EmailWorkersPool{
		ctx: ctx,
	}

	go func() {
		<-ctx.Done()
		pool.StopWorkers()
		log.Println("Email workers pool stopped")
	}()

	return pool
}

// Starts or restarts the email workers state.
func (s *EmailWorkersPool) Set(
	ips []GoStateIp,
	workersPerIp int,
) {

	s.StopWorkers()

	s.mu.Lock()
	defer s.mu.Unlock()

	ctx, cancel := context.WithCancel(s.ctx)
	s.cancelFunc = cancel

	log.Printf("Starting %d email workers for %d IPs\n", len(ips)*workersPerIp, len(ips))

	for i, ip := range ips {
		s.wg.Add(1)
		go emailWorker(ctx, i, &s.wg, ip.Ip, ip.Ptr, ip.QueueId, ip.QueueName)
	}

}

func (s *EmailWorkersPool) StopWorkers() {

	s.mu.Lock()
	defer s.mu.Unlock()

	if s.cancelFunc != nil {
		s.cancelFunc()
		s.cancelFunc = nil
	}

	s.wg.Wait()

}

func emailWorker(
	ctx context.Context,
	id int,
	wg *sync.WaitGroup,

	ip string,
	ptr string,
	queueId int,
	queueName string,
) {
	defer wg.Done()

	// TODO: implement reconnection logic
	conn, err := NewDbConn()

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

			sends, err := batch.FetchSends(queueId)

			if err != nil {
				log.Printf("Worker %d failed to get send IDs: %v\n", id, err)
				time.Sleep(1 * time.Second)
				batch.Rollback()
				continue
			}

			if len(sends) == 0 {
				batch.Rollback()
			}

			// log.Printf("Worker %d processing %d sends from queue %s\n", id, len(sends), queueName)

			for _, send := range sends {

				log.Printf("Worker %d processing send ID %d from %s to %s\n", id, send.Id, send.From, send.To)

				result := sendEmail(&send, os.Stdout)

				if result.Error != nil {
					log.Printf("Worker %d failed to send email for ID %d: %v\n", id, send.Id, result.Error)
				} else {
					log.Printf("Worker %d successfully sent email for ID %d to host %s\n", id, send.Id, result.SentHost)
				}

			}

			batch.Commit()
			time.Sleep(1 * time.Second)

		}
	}
}
