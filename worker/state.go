package main

import (
	"context"
	"log/slog"
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

func (g GoState) IpAddresses() []string {
	ips := make([]string, len(g.Ips))
	for i, ip := range g.Ips {
		ips[i] = ip.Ip
	}
	return ips
}

func (g GoState) IpQueueMapString() string {
	val := ""
	for _, ip := range g.Ips {
		if val != "" {
			val += ","
		}
		val += ip.Ip + ":" + ip.QueueName
	}
	return val
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
	IncomingMailServer *IncomingMailServer
	DnsServer          *DnsServer

	// whether the service is initialized for the first time
	IsSet bool
}

func NewServiceState(ctx context.Context, logger *slog.Logger) *ServiceState {
	metricsServer := NewMetricsServer(ctx, logger)

	return &ServiceState{
		ctx:                ctx,
		Logger:             logger,
		MetricsServer:      metricsServer,
		EmailWorkersPool:   NewEmailWorkersPool(ctx, logger, metricsServer.metrics),
		WebhookWorkersPool: NewWebhookWorkersPool(ctx, logger, metricsServer.metrics),
		IncomingMailServer: NewIncomingMailServer(ctx, logger),
		DnsServer:          NewDnsServer(ctx, logger),
	}
}

func (s *ServiceState) Set(goState GoState) {

	s.Logger.Info("Updating worker state",
		"hostname", goState.Hostname,
		"ip_count", len(goState.Ips),
		"ip_queues", goState.IpQueueMapString(),
		"dns_records_count", len(goState.DnsRecords),
		"email_workers_ip_per", goState.EmailWorkersPerIp,
		"webhook_workers", goState.WebhookWorkers,
		"incoming_workers", goState.IncomingWorkers,
		"is_leader", goState.IsLeader,
		"env", goState.Env,
		"version", goState.Version,
	)

	s.EmailWorkersPool.Set(goState.Ips, goState.EmailWorkersPerIp, goState.InstanceDomain)
	s.WebhookWorkersPool.Set(goState.WebhookWorkers)
	s.IncomingMailServer.Set(goState.InstanceDomain, goState.IncomingWorkers)
	s.DnsServer.Set(goState.DnsIp, goState.DnsRecords)
	s.MetricsServer.Set(goState)

	s.IsSet = true

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
