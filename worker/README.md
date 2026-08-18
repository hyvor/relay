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

## Retries

Three things back off and retry in the worker, and all three share the jitter
helpers in `retry.go`:

| What | Ladder | Defined in |
| --- | --- | --- |
| Deferred sends | 15m, 1h, 2h, 4h, 8h, 16h, then 1d | `getSendRetryDelay()` in `send.go` |
| Failed webhook deliveries | 1m, 5m, 15m, 1h, 4h, then 1d | `getWebhookRetryDelay()` in `webhooks_pg.go` |
| Database reconnects | 100ms doubling up to 10s | `createNewRetryingDbConn()` in `pg.go` |

Each delay is spread by `retryJitterFactor` (+/- 15%) before it is used. The
ladders themselves stay deterministic; only the wake-up instant moves.

Without the jitter, everything that failed in the same tick comes back in the
same tick: a batch of sends deferred by one MX server retries as a batch, a
customer endpoint that goes down gets its entire queued burst returned at once,
and a Postgres blip has every worker reconnecting in lockstep.

Tests pin `jitterSource` rather than asserting on random output. See
`withJitterSource()` in `retry_test.go`.
