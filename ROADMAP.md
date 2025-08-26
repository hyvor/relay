Features to implement:

- [ ] Incoming email routing
- [ ] Send via SMTP
- [ ] Dedicated IPs for users
- [ ] Custom DKIM selector

Features we will not implement:

- In-built authentication: Since our Cloud depends on HYVOR authentication from hyvor.com, we do not plan to implement an in-built authentication system within Relay. That would be redundant. OpenID Connect will be the only authentication method available for self-hosted Relay instances.
- No in-built open or click tracking: This is against HYVOR's privacy standards. Fork Relay and implement it if you want! Also, it wouldn't be too hard to implement at the app-level.
- Templates
