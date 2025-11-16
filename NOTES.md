## Testing

### Incoming Bounce Mails

```
swaks --to someone@example.com --from supun@hyvor.com \
  --server localhost --port 11125
```

Sending with a body:

```
cd meta/test_emails
swaks --server localhost --port 11125 --body @arf.txt --to someone@example.com --from someone@hyvor.com
```

## Sending Emails via SMTP

```
swaks \
  --to to@example.com \
  --from test@hyvor.local.testing \
  --server localhost \
  --port 11125 \
  --body="<p>Hello  World</p>" \
  --add-header "Content-Type: text/html" \
  --add-header "X-Relay-Test: true" \
  --auth-password test-api-key \
  --auth-user relay
```

## Server Requirements

-   Assume one SMTP message takes 1 second to process.
-   One worker can process 86400 messages per day.
-   50 workers (which is totally possible with a 4GB PGSQL + 4GB App server) can process 4,320,000 million messages per day.
-   8GB Postgres can have
