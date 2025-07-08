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
