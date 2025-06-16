package main

import (
	"context"
	"log"
	"sync"
	"time"
)

type EmailWorkersState struct {
	mu         sync.Mutex
	wg         sync.WaitGroup
	cancelFunc context.CancelFunc
}

func NewEmailWorkersState() *EmailWorkersState {
	return &EmailWorkersState{}
}

// Starts or restarts the email workers state.
func (s *EmailWorkersState) Start(workers_count int) {

	s.mu.Lock()
	defer s.mu.Unlock()

	if s.cancelFunc != nil {
		s.cancelFunc()
		s.wg.Wait()
	}

	ctx, cancel := context.WithCancel(context.Background())
	s.cancelFunc = cancel

	for i := 0; i < workers_count; i++ {
		s.wg.Add(1)
		go emailWorker(ctx, i, &s.wg)
	}

}

func (s *EmailWorkersState) StopWorkers() {

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
