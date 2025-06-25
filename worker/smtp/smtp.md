Copied from net/smtp (https://cs.opensource.google/go/go/+/refs/tags/go1.24.4:src/net/smtp/smtp.go)

Modifications:

- All commands return a Reply struct instead of just error. This gives access to the raw server response.
- Removed the Client.SendMail function
- Removed the Client.Auth method
- Removed the Client.Verify method (nobody supports it)
- Implements enhanced status codes as per RFC 3463
- Does not validate automatically for server status (e.g. 250 for successful commands).
- Other methods do not implicitly call Hello
