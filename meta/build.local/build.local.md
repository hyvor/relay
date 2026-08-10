Builds the production image locally and runs it with the hyvor/dev Traefik setup. Visit https://relay.build.hyvor.localhost to test.

```bash
# in hyvor/dev folder, run to start dex OIDC:
docker compose --profile oidc up -d

# in relay, run to start relay
docker compose -f meta/build.local/compose.build.yaml up --build
```

## SMTP & DNS

The SMTP (25, 587) and DNS (53) servers are exposed on host ports 12025, 12587, and 12053/udp.

Test incoming SMTP (requires `swaks`):

```bash
swaks --to user@example.com --server localhost:12025
```
