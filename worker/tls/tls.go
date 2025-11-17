package tls

// Generates TLS certificates using Let's Encrypt for the (incoming) email server (STARTTLS)
// Note that web server TLS is handled by Caddy (FrankenPHP)

import (
	"context"
	"fmt"

	"github.com/caddyserver/certmagic"
	"github.com/libdns/libdns"
)

// implements certmagic.DNSProvider
type DnsProvider struct{}

func (d *DnsProvider) AppendRecords(ctx context.Context, zone string, recs []libdns.Record) ([]libdns.Record, error) {

	fmt.Println("Appending DNS records for zone:", zone)
	fmt.Println("Records:", recs)

	return recs, nil
}

func (d *DnsProvider) DeleteRecords(ctx context.Context, zone string, recs []libdns.Record) ([]libdns.Record, error) {

	fmt.Println("Deleting DNS records for zone:", zone)
	fmt.Println("Records:", recs)

	return recs, nil
}



func generateTLSCertificates() error {

	provider := &DnsProvider{}

	magic := certmagic.NewDefault()
	magic.Storage = &DbStorage{}

	certmagic.DefaultACME.CA = certmagic.LetsEncryptStagingCA

	certmagic.DefaultACME.DNS01Solver = &certmagic.DNS01Solver{
        DNSManager: certmagic.DNSManager{
			DNSProvider: provider,
		},
    }

	domain := "mail.hyvor-relay.com"
	err := magic.ManageSync(nil, []string{domain})

	if err != nil {
		return err
	}

	fmt.Println("TLS certificates generated successfully for domain:", domain)

	return nil
}