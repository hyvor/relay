The worker is composed of the following components:

// TODO

-
-

## Development

- After making changes to the worker code, restart the container.
- `make test` to run tests.
- When running some tests, you may need to uncomment the `command: "sleep infinity"` line in the `compose.yaml` file to prevent the worker from running and taking up ports.
- `make coverage` to run tests with coverage. Open `coverage.html` in a browser to view the coverage report.
