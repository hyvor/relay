## Hyvor Relay

## Architecture

Basic architecture of the service:

- Central PGSQL database for storing emails and metadata
- Symfony for the API
- Go for the workers

Servers:

- API servers: handles HTTP requests
- Worker servers: process queued emails.

Lifecycle of an email:

- User sends an email via API
- API server validates and queues the email
- Workers send the queued emails

Adding a worker server:

- First, you add the worker server in the configuration with a unique worker ID.
- Then, you start the worker server with the same `WORKER_ID` env.
- Once the server is ready for consuming emails (pings are sent to the database), you can start the warmup process.
- It will eventually (in a couple of days) reach the maximum sending rate defined in the configuration.
