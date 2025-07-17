package main

import (
	"context"
	"log/slog"
	"strings"

	"github.com/miekg/dns"
)

type DnsServer struct {
	ctx    context.Context
	logger *slog.Logger

	serverStarted bool

	// data
	instanceDomain string
	ipPtrsForward  map[string]string // maps domain names to IP addresses
	mxIps          []string          // list of IPs for MX records (usually one IP per server)
}

func NewDnsServer(ctx context.Context, logger *slog.Logger) *DnsServer {

	return &DnsServer{
		ctx:           ctx,
		logger:        logger,
		ipPtrsForward: make(map[string]string),
	}

}

func (s *DnsServer) Set(
	instanceDomain string,
	ipPtrsForward map[string]string,
	mxIps []string,
) {

	s.instanceDomain = instanceDomain
	s.ipPtrsForward = ipPtrsForward
	s.mxIps = mxIps

	if !s.serverStarted {
		s.serverStarted = true

		go func() {

			dns.HandleFunc(".", s.handleRequest)

			server := &dns.Server{Addr: ":53", Net: "udp"}
			s.logger.Info("Starting DNS server on :53")

			if err := server.ListenAndServe(); err != nil {
				s.logger.Error("Failed to start DNS server", "error", err)
			}

		}()

		go func() {
			<-s.ctx.Done()
			s.logger.Info("Shutting down DNS server")
			dns.HandleRemove(".")
		}()

	}

}

func (s *DnsServer) handleRequest(w dns.ResponseWriter, r *dns.Msg) {
	msg := new(dns.Msg)
	msg.SetReply(r)
	msg.Authoritative = true

	for _, q := range r.Question {

		name := strings.ToLower(q.Name)
		name = strings.TrimSuffix(name, ".")

		if q.Qtype == dns.TypeA {

			if ip, ok := s.ipPtrsForward[name]; ok {
				rr, err := dns.NewRR(q.Name + " 3600 IN A " + ip)
				if err == nil {
					msg.Answer = append(msg.Answer, rr)
				} else {
					s.logger.Error("Failed to create DNS record", "error", err)
				}
				continue
			}

			if name == "mx."+s.instanceDomain {
				for _, ip := range s.mxIps {
					rr, err := dns.NewRR(q.Name + " 3600 IN A " + ip)
					if err == nil {
						msg.Answer = append(msg.Answer, rr)
					} else {
						s.logger.Error("Failed to create MX record", "error", err)
					}
				}
				continue
			}

		} else if q.Qtype == dns.TypeMX && name == s.instanceDomain {

			rr, err := dns.NewRR(q.Name + " 3600 IN MX 10 mx." + s.instanceDomain + ".")
			if err == nil {
				msg.Answer = append(msg.Answer, rr)
			} else {
				s.logger.Error("Failed to create MX record", "error", err)
			}
			continue

		} else if q.Qtype == dns.TypeTXT && name == s.instanceDomain {

			allIps := make([]string, 0, len(s.ipPtrsForward))
			for _, ip := range s.ipPtrsForward {
				allIps = append(allIps, ip)
			}

			// TODO: adding each IP can exceed DNS TXT record size limit
			// We need a way to handle this. See #106 (https://github.com/hyvor/relay/issues/106)

			spf := "v=spf1 ip4:" + strings.Join(allIps, " ip4:") + " -all"

			rr, err := dns.NewRR(q.Name + " 3600 IN TXT \"" + spf + "\"")
			if err == nil {
				msg.Answer = append(msg.Answer, rr)
			} else {
				s.logger.Error("Failed to create TXT record", "error", err)
			}
			continue

		}

	}

	w.WriteMsg(msg)
}
