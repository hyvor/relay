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

## Worker metrics

`workers_email_total`, `workers_webhook_total` and `workers_incoming_mail_total`
are owned by the worker goroutines themselves: each one `Inc()`s as it starts
and `Dec()`s through a `defer` as it exits. `MetricsServer.Set()` must never
write to them.

That means the gauges report workers that are actually running, so they drop
when a worker dies (for example when it cannot reach the database) and briefly
show two generations while a state update is draining the old one. Reporting
the configured count instead would hide both.

`workers_api_total` is the exception and is still set from `GoState`. The API
workers are PHP processes owned by the backend, which this binary cannot
observe.
