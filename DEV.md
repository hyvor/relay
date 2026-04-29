### Running Hyvor Relay

- First, configure [hyvor/dev](https://github.com/hyvor/dev?tab=readme-ov-file#first-time-setup), our development
  environment. `./services` starts Traefik and Postgres.
- Then, run the following command to run Hyvor Relay locally via Docker Compose:

```bash
./run relay
```

### Database setup

Run the following to reset the database and seed it with sample data:

```bash
# from `backend` docker container:
bin/console dev:reset --seed

# or, from host machine:
docker compose exec -it backend bash -c "bin/console dev:reset --seed"
```


### Sending an email with curl

"Test Project" is seeded with an API key 'test-api-key', which you can use for testing. Then, check Sends in Console for
logs.

```bash
curl -X POST https://relay.hyvor.localhost/api/console/sends \
     -H "Authorization: Bearer test-api-key" \
     -H "Content-Type: application/json" \
     -d '{
           "from": "test@hyvor.local.testing",
           "to": "accept@simulator.net",
           "subject": "Hello from Hyvor Relay",
           "body_text": "This is a test email sent via the API."
         }'
```

### Testing with the Simulator

Use [hyvor/smtp-simulator](https://github.com/hyvor/smtp-simulator) for testing bounce scenarios locally.

```bash
# 1. run simulator
docker compose up -d simulator

# 2. send emails to @simulator.net
# - tempfail@simulator.com
# - missing@simulator.com
# more: https://github.com/hyvor/smtp-simulator?tab=readme-ov-file#email-addresses
```

Note: the simulator can send back bounce (DSN) and complaint (ARF) messages back to Go email server.
