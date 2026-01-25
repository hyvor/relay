> [!WARNING]
> Our SDKS only support email sending at the moment. Other API endpoints, such as domain management, are not planned to be supported in the near term.

This document is the main playbook for SDKs. The Javascript library is the primary implementation. Any updates to this document must be first implemented in the Javascript library.

## 1. RelayClient

Users would initiate the `RelayClient` as the entrypoint:

```ts
import { RelayClient } from '@hyvor/relay';

const client = new RelayClient({

    // Hyvor Relay Console API key
    apiKey: '',

    // which Hyvor Relay instance to call
    // the default must be the cloud URL as shown here
    baseUrl: 'https://relay.hyvor.com',

    // ===== TIMEOUT ======

    // How long to wait (in milliseconds) when establishing a connection (default 5s)
    connectionTimeoutMs: 5000,
    // How long to wait (in milliseconds) for a response before aborting a request (default 30s)
    requestTimeoutMs: 30000,

    // ===== RETRYING =====

    // Total retry attempts (1 initial + 2 retries)
    // <= 1 to disable retries
    retryMaxAttempts: 3,
    // Wait time before the first retry (default 1s)
    retryInitialDelayMs: 1000,
    // Cap the wait time to keep the UX snappy
    retryMaxDelayMs: 10000,
    // Exponential factor (delay = initialDelay * factor ^ attempt)
    retryBackoffFactor: 2

})
```

