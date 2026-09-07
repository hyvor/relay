# cache decisions

- use `ttlcache/v3` for bounded in-memory caching with expiry
- use a separate Symfony cache pool so existing `cache.app` data is untouched
- store shared values as JSON so Go and PHP can read them
- use the `relay-shared-v1` namespace and encoded keys
- preserve the original expiry when moving values from PostgreSQL to memory
- reject invalid or oversized values
- cache integration tests need the PostgreSQL test service
