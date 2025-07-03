package main

import (
	"log"

	"github.com/miekg/dns"
)

func handleRequest(w dns.ResponseWriter, r *dns.Msg) {
	m := new(dns.Msg)
	m.SetReply(r)

	for _, q := range r.Question {
		switch q.Qtype {
		case dns.TypeA:
			log.Printf("Received query for: %s", q.Name)
			rr, err := dns.NewRR(q.Name + " 3600 IN A 127.0.0.1")
			if err == nil {
				m.Answer = append(m.Answer, rr)
			}
		}
	}

	err := w.WriteMsg(m)
	if err != nil {
		log.Printf("Failed to write response: %v", err)
	}
}

func main() {
	dns.HandleFunc(".", handleRequest)

	server := &dns.Server{Addr: ":5352", Net: "udp"}
	log.Printf("Starting DNS server on :5352")
	err := server.ListenAndServe()
	if err != nil {
		log.Fatalf("Failed to start server: %s", err.Error())
	}
}
