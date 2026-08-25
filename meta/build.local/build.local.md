Builds the production image locally and runs it with the hyvor/dev Traefik setup. Visit https://relay.build.hyvor.localhost to test.

## Setup

```bash
# in hyvor/dev folder, run to start dex OIDC:
docker compose --profile oidc up -d

# in relay, run to start relay
docker compose -f meta/build.local/compose.build.yaml up --build
```

## Testing

The SMTP (25, 587) and DNS (53) servers are exposed on host ports 12025, 12587, and 12053/udp.

### Incoming SMTP

```bash
swaks --to user@relay.build.hyvor.localhost --server localhost:12025
```

### Outgoing SMTP

```bash
curl -X POST https://relay.build.hyvor.localhost/api/console/sends \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer <api-key>' \
  -d '{"from": "test@relay.build.hyvor.localhost", "to": "user@example.com", "subject": "Test", "body_html": "<p>Test</p>"}'
```
