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
