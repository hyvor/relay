# cache decisions

- use `ttlcache/v3` for bounded in-memory caching with expiry
- use a separate Symfony cache pool so existing `cache.app` data is untouched
- store shared values as JSON so Go and PHP can read them
- use the `relay-shared-v1` namespace and encoded keys
- preserve the original expiry when moving values from PostgreSQL to memory
- reject invalid or oversized values
- cache integration tests need the PostgreSQL test service

# DNS decisions

- use RFC 8484 DNS-over-HTTPS for outbound MX, A, and AAAA lookups
- require HTTPS DoH endpoints; configure with `DNS_OVER_HTTPS_URL`
- default to Cloudflare's `https://cloudflare-dns.com/dns-query` endpoint
- validate the DNS response question and reject redirects, invalid, oversized, or non-DNS responses
- use the DNSSEC authenticated-data flag when supplied by the DoH resolver
- cache MX records as JSON under `mx:<domain>` with TTL capped at one hour
- sort MX hosts by preference and reject Null MX records
- fall back from an empty MX response to A, then AAAA records for implicit MX
- DoH failures are SMTP MX lookup failures; cache failures do not fail delivery
- reject non-success MX DNS responses instead of treating them as implicit-MX absence
- retain the resolver's DNSSEC authenticated-data state in cached MX values
- validate cached MX records before using them for delivery
- query TLSA at `_25._tcp.<mx-host>` through the same DoH resolver
- cache TLSA data under a versioned host-specific key with a one-hour TTL cap
- distinguish secure TLSA records, secure absence, secure unusable records, and insecure answers
- do not treat transient DNS errors as authenticated TLSA absence
- validate TLSA selectors, matching types, and association-data lengths before caching
- support TLSA certificate and SPKI selectors with full, SHA-256, and SHA-512 matching
- allow DANE-EE authentication without public-CA validation
- require certificate-chain validation for DANE-TA and PKIX TLSA usages
- never treat a TLSA association mismatch as a reason to downgrade to plaintext
- require STARTTLS when authenticated TLSA records are present
- only apply TLSA policy when the corresponding MX or implicit-MX answer was DNSSEC authenticated
- run DANE certificate verification during the TLS handshake, before `MAIL FROM`
- treat missing STARTTLS, TLSA lookup failures, and certificate mismatches as temporary delivery failures on the first attempt
- refresh the process cache database handle when email workers restart
