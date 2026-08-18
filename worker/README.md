## Development

- After making changes to the worker code, restart the container to re-run the worker.
- `make test` to run tests (restarting not required).
- `make coverage` to run tests with coverage. Open `coverage.html` in a browser to view the coverage report.

## Simulator Testing

Run relay with the SMTP simulator:

```bash
./run relay --profile simulator
```

Then, send emails to `address@simulator.net`.
See [available addresses](https://github.com/hyvor/smtp-simulator?tab=readme-ov-file#email-addresses).

## Outgoing TLS

Delivery uses opportunistic STARTTLS: if the receiving server advertises the
extension, `sendEmailToHostWithTlsMaxVersion()` upgrades the connection before
`MAIL FROM`.

Some receiving servers abort the handshake when they see a TLS 1.3
`ClientHello`, even though they would negotiate TLS 1.2 without complaint. When
that happens the host is dialled a second time with `MaxVersion` pinned to
`tlsFallbackMaxVersion`. A fresh connection is required, because STARTTLS has
already been issued on the failed one.

Two things are easy to get wrong here:

- The handshake is **lazy**. `tls.Client()` returns immediately, so a rejected
  `ClientHello` surfaces as an error from the EHLO that follows STARTTLS, not
  from `StartTLS()` itself.
- Not every TLS error is worth a retry. Certificate failures are excluded,
  because a lower version cannot fix an untrusted certificate and retrying
  would double the connections to every misconfigured host. See
  `isTlsVersionNegotiationError()` for the error shapes involved; they are not
  uniform, and the test suite drives real handshakes rather than constructing
  error values.
