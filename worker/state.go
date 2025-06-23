package main

import (
	"context"
	"log/slog"
	"os"
	"time"
)

// GoState object from backend
type GoState struct {
	Hostname          string      `json:"hostname"`
	Ips               []GoStateIp `json:"ips"`
	EmailWorkersPerIp int         `json:"emailWorkersPerIp"`
	WebhookWorkers    int         `json:"webhookWorkers"`
}

type GoStateIp struct {
	Ip        string `json:"ip"`
	Ptr       string `json:"ptr"`
	QueueId   int    `json:"queueId"`
	QueueName string `json:"queueName"`
	Incoming  bool   `json:"incoming"`
}

// wraps all the services based on the GoState
type ServiceState struct {
	ctx              context.Context
	Logger           *slog.Logger
	EmailWorkersPool *EmailWorkersPool
}

func (s *ServiceState) Set(goState GoState) {
	s.EmailWorkersPool.Set(goState.Ips, goState.EmailWorkersPerIp)

	s.Logger.Info("Updating state",
		"hostname", goState.Hostname,
		"ip_count", len(goState.Ips),
		"ips", goState.Ips,
		"email_workers_count", len(goState.Ips),
	)

	for _, ip := range goState.Ips {
		s.Logger.Info("IP info",
			"ip", ip.Ip,
			"ptr", ip.Ptr,
			"queueId", ip.QueueId,
			"queueName", ip.QueueName,
			"incoming", ip.Incoming,
		)
	}
}

func (s *ServiceState) Initialize() {

	go func() {
		for {
			err := s.doInitialize()
			if err != nil {
				s.Logger.Error("Failed to initialize service state, retrying in 2 seconds: " + err.Error())

				select {
				case <-s.ctx.Done():
					return
				case <-time.After(2 * time.Second):
				}
			} else {
				return
			}
		}
	}()

}

func (s *ServiceState) doInitialize() error {
	var goState GoState
	err := CallLocalApi(s.ctx, "GET", "/state", nil, &goState)

	if err != nil {
		return err
	}

	s.Set(goState)

	return nil
}

func NewServiceState(ctx context.Context) *ServiceState {
	return &ServiceState{
		ctx:              ctx,
		Logger:           slog.New(slog.NewTextHandler(os.Stdout, nil)),
		EmailWorkersPool: NewEmailWorkersPool(ctx),
	}
}
