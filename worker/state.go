package main

import (
	"context"
	"log/slog"
	"os"
	"time"
)

// GoState object from backend
type GoState struct {
	InstanceDomain    string      `json:"instanceDomain"`
	Hostname          string      `json:"hostname"`
	Ips               []GoStateIp `json:"ips"`
	EmailWorkersPerIp int         `json:"emailWorkersPerIp"`
	WebhookWorkers    int         `json:"webhookWorkers"`
	IncomingWorkers   int         `json:"incomingWorkers"`
	IsLeader          bool        `json:"isLeader"`

	DnsIp      string             `json:"dnsIp"`
	DnsRecords []GoStateDnsRecord `json:"dnsRecords"`

	ServersCount int    `json:"serversCount"`
	Env          string `json:"env"`
	Version      string `json:"version"`
}

type GoStateIp struct {
	Id        int    `json:"id"`
	Ip        string `json:"ip"`
	Ptr       string `json:"ptr"`
	QueueId   int    `json:"queueId"`
	QueueName string `json:"queueName"`
}

type GoStateDnsRecord struct {
	Type     string `json:"type"`
	Host     string `json:"host"`
	Content  string `json:"content"`
	TTL      int    `json:"ttl"`
	Priority int    `json:"priority"`
}

// wraps all the services based on the GoState
type ServiceState struct {
	ctx                context.Context
	Logger             *slog.Logger
	MetricsServer      *MetricsServer
	EmailWorkersPool   *EmailWorkersPool
	WebhookWorkersPool *WebhookWorkersPool
	BounceServer       *BounceServer
	DnsServer          *DnsServer

	// whether the service is initialized for the first time
	IsSet bool
}

func NewServiceState(ctx context.Context) *ServiceState {
	logger := slog.New(slog.NewTextHandler(os.Stdout, nil))
	metricsServer := NewMetricsServer(ctx, logger)

	return &ServiceState{
		ctx:                ctx,
		Logger:             logger,
		MetricsServer:      metricsServer,
		EmailWorkersPool:   NewEmailWorkersPool(ctx, logger, metricsServer.metrics),
		WebhookWorkersPool: NewWebhookWorkersPool(ctx, logger),
		BounceServer:       NewBounceServer(ctx, logger),
		DnsServer:          NewDnsServer(ctx, logger),
	}
}

func (s *ServiceState) Set(goState GoState) {

	s.EmailWorkersPool.Set(goState.Ips, goState.EmailWorkersPerIp, goState.InstanceDomain)
	s.WebhookWorkersPool.Set(goState.WebhookWorkers)
	s.BounceServer.Set(goState.InstanceDomain, goState.IncomingWorkers)

	if goState.DnsIp != "" {
		s.DnsServer.Set(
			goState.DnsIp,
			goState.DnsRecords,
		)
	}

	s.MetricsServer.Set(goState)

	s.Logger.Info("Updating state",
		"hostname", goState.Hostname,
		"ip_count", len(goState.Ips),
		"ips", goState.Ips,
		"dns_records", goState.DnsRecords,
		"email_workers_ip_per", goState.EmailWorkersPerIp,
	)

	for _, ip := range goState.Ips {
		s.Logger.Info("IP info",
			"ip", ip.Ip,
			"ptr", ip.Ptr,
			"queueId", ip.QueueId,
			"queueName", ip.QueueName,
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
