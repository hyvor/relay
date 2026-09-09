# Mail Delivery

## Cache

Two cache layers are used:

- `ttlcache/v3` for fast, process-local reads. It is limited to 10,000 items
  and 32 MB.
- PostgreSQL for sharing values between Go and PHP.

The PostgreSQL cache uses the Symfony pool `cache.shared` and the `shared-v1`
namespace. Both languages use the same key and value format:

```text
shared-v1:h.<sha256>
```

Values are JSON and limited to 1 MB. When a database value is loaded into
memory, its remaining TTL is preserved. Singleflight combines concurrent loads,
and generations stop an old load from restoring a deleted value.

## DNS and MX

DNS-over-HTTPS (DoH) is used for outbound DNS. The endpoint must use HTTPS and
can be changed with `DNS_OVER_HTTPS_URL`. The default is Cloudflare DoH.

DNS responses are validated before use. The cache key includes the DoH
endpoint, so answers from one resolver are not reused for another resolver.
The DNSSEC authenticated-data flag is kept with the cached result.

MX records are sorted by priority. Null MX and failed MX responses are not
usable for delivery. If a successful MX response has no MX records, an
implicit MX from A or AAAA records is used. CNAME loops and incomplete
responses fail closed.

Cache TTLs use the shortest relevant DNS TTL. Negative answers also use the
SOA negative-cache lifetime. MX and TLSA entries are capped at one hour.

## MTA-STS

The `_mta-sts.<domain>` TXT record is checked for a valid `STSv1` value. When
present, the policy is fetched from:

```text
https://mta-sts.<domain>/.well-known/mta-sts.txt
```

`mode: enforce` requires TLS. `testing` and `none` stay opportunistic. If the
policy has `mx:` entries, the resolved MX host must match one of them.
Policies are cached for `max_age`, capped at one hour. A policy fetch failure
is a temporary delivery failure, not a reason to use plaintext.

## DANE and TLSA

TLSA records are queried at `_25._tcp.<mx-host>`. The TLSA result is applied
when the related MX answer is DNSSEC authenticated.

The lookup distinguishes usable records, secure absence, unusable secure
records, and insecure answers. Transient DNS errors are not treated as secure
absence. TLSA fields and certificate association data are checked before
caching.

DANE-EE (usage 3) and DANE-TA (usage 2) are supported, including certificate and
SPKI selectors with full, SHA-256, and SHA-512 matching. DANE-EE checks the
peer certificate. DANE-TA checks the certificate chain against the matching
trust anchor.

Certificate checks run during the TLS handshake, before `MAIL FROM`. A TLSA
mismatch or missing STARTTLS never falls back to plaintext. Secure but unusable
TLSA records still require encryption, but do not require DANE authentication.

## STARTTLS

STARTTLS is opportunistic by default. If the server rejects STARTTLS or the
handshake fails, the connection is retried without TLS.

MTA-STS `enforce` and authenticated DANE TLSA records disable this fallback.
Those failures become temporary delivery failures instead.

The final SMTP DATA response has a longer timeout because the receiving server
may scan the message before replying. This reduces unnecessary duplicate
delivery attempts.
