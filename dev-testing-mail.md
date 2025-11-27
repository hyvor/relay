When running dev, the (incoming) mail server is available at localhost:11025.

### Incoming Bounce Mails

Install `swaks` if you don't have it already (https://jetmore.org/john/code/swaks/)

```
swaks --to someone@example.com --from supun@hyvor.local.testing \
  --server localhost --port 11025
```

Sending with a body:

```
cd meta/test_emails
swaks --server localhost --port 11025 --body @arf.txt --to someone@example.com --from someone@hyvor.com
```

## Sending Emails via SMTP

```
swaks \
  --to to@example.com \
  --from test@hyvor.local.testing \
  --server localhost \
  --port 11025 \
  --body="<p>Hello  World</p>" \
  --add-header "Content-Type: text/html" \
  --add-header "X-Relay-Test: true" \
  --auth-password test-api-key \
  --auth-user relay \
  --tls
```

### Testing STARTTLS
