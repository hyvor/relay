## Testing

### Incoming Bounce Mails

```
swaks --to someone@example.com --from supun@hyvor.com \
  --server localhost --port 10025
```

Sending with a body:

```
cd meta/test_emails
swaks --server localhost --port 10025 --body @arf.txt --to someone@example.com --from someone@hyvor.com
```

## Server Requirements

- Assume one SMTP message takes 1 second to process.
- One worker can process 86400 messages per day.
- 50 workers (which is totally possible with a 4GB PGSQL + 4GB App server) can process 4,320,000 million messages per day.
- 8GB Postgres can have
