package main

import (
	"context"
	"fmt"
	"log/slog"
	"strings"

	"github.com/miekg/dns"
)

type DnsServer struct {
	ctx     context.Context
	logger  *slog.Logger
	metrics *Metrics

	server     *dns.Server
	dnsRecords []GoStateDnsRecord
}

func NewDnsServer(ctx context.Context, logger *slog.Logger, metrics *Metrics) *DnsServer {

	return &DnsServer{
		ctx:     ctx,
		logger:  logger.With("component", "dns_server"),
		metrics: metrics,
	}

}

func (s *DnsServer) Set(
	dnsIp string,
	dnsRecords []GoStateDnsRecord,
) {

	s.dnsRecords = dnsRecords

	s.StopServer()
	s.StartServer(dnsIp)

}

func (s *DnsServer) StartServer(dnsIp string) {

	go func() {

		dns.HandleFunc(".", s.handleRequest)

		addr := dnsIp + ":53"
		server := &dns.Server{Addr: addr, Net: "udp"}
		s.server = server

		s.logger.Info("Starting DNS server on " + addr)
		if err := server.ListenAndServe(); err != nil {
			s.logger.Error("Failed to start DNS server", "error", err)
		}

	}()

	go func() {
		<-s.ctx.Done()
		s.StopServer()
	}()

}

func (s *DnsServer) StopServer() {

	if s.server != nil {
		if err := s.server.Shutdown(); err != nil {
			s.logger.Error("Failed to stop DNS server", "error", err)
		} else {
			s.logger.Info("DNS server stopped")
		}
		s.server = nil
	}

}

func (s *DnsServer) handleRequest(w dns.ResponseWriter, r *dns.Msg) {
	msg := new(dns.Msg)
	msg.SetReply(r)
	msg.Authoritative = true

	for _, q := range r.Question {

		name := strings.ToLower(q.Name)
		name = strings.TrimSuffix(name, ".")

		records := s.findDnsRecordsByTypeAndHost(dns.TypeToString[q.Qtype], name)
		found := len(records) > 0

		status := "not_found"
		if found {
			status = "found"
		}
		s.metrics.dnsQueriesTotal.WithLabelValues(dns.TypeToString[q.Qtype], status).Inc()

		if !found {
			s.logger.Warn("No DNS records found for query", "name", name, "type", dns.TypeToString[q.Qtype])
			continue
		}

		for _, record := range records {

			recordStr := ""

			switch strings.ToUpper(record.Type) {
			case "A":
				recordStr = fmt.Sprintf("%s %d IN A %s", name, record.TTL, record.Content)
			case "AAAA":
				recordStr = fmt.Sprintf("%s %d IN AAAA %s", name, record.TTL, record.Content)
			case "CNAME":
				recordStr = fmt.Sprintf("%s %d IN CNAME %s", name, record.TTL, dns.Fqdn(record.Content))
			case "MX":
				recordStr = fmt.Sprintf("%s %d IN MX %d %s", name, record.TTL, record.Priority, dns.Fqdn(record.Content))
			case "TXT":
				recordStr = fmt.Sprintf("%s %d IN TXT \"%s\"", name, record.TTL, record.Content)
			default:
				s.logger.Warn("Unsupported record type: %s", record.Type)
				continue
			}

			rr, err := dns.NewRR(recordStr)
			if err != nil {
				s.logger.Error("Failed to create DNS record", "error", err, "record", recordStr)
				continue
			}

			msg.Answer = append(msg.Answer, rr)
		}

	}

	w.WriteMsg(msg)
}

func (s *DnsServer) findDnsRecordsByTypeAndHost(recordType, host string) []*GoStateDnsRecord {
	var records []*GoStateDnsRecord
	for _, record := range s.dnsRecords {
		if record.Type == recordType && record.Host == host {
			records = append(records, &record)
		}
	}
	return records
}
