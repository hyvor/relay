package main

import (
	"context"
	"log"
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
func (s *EmailWorkersPool) Set(workersCount int) {

	s.StopWorkers()

	s.mu.Lock()
	defer s.mu.Unlock()

	ctx, cancel := context.WithCancel(s.ctx)
	s.cancelFunc = cancel

	for i := 0; i < workersCount; i++ {
		s.wg.Add(1)
		go emailWorker(ctx, i, &s.wg)
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
	log.Println("All email workers stopped")

}

func emailWorker(ctx context.Context, id int, wg *sync.WaitGroup) {
	defer wg.Done()
	log.Printf("Worker %d started\n", id)
	for {
		select {
		case <-ctx.Done():
			log.Printf("Worker %d stopping\n", id)
			return
		default:
			// Simulate work
			log.Printf("Worker %d working...\n", id)
			time.Sleep(1 * time.Second)
		}
	}
}
