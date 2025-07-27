Features to implement:

- [ ] Dedicated IPs for users
- [ ] CC, BCC support
- [ ] Incoming email routing

Features we will not implement:

- In-built authentication: Since our Cloud depends on HYVOR authentication from hyvor.com, we do not plan to implement an in-built authentication system within Relay. That would be redundant. OpenID Connect will be the only authentication method available for self-hosted Relay instances.
