Rules for developing a SDK:

- The SDK must be based on the Javascript SDK.
  - all the features supported by the JS SDK must be supported
  - configs should match as much as possible with the JS SDK
- At this moment, only email sending (POST /sends) is supported via the SDKs. Other endpoints, such as creating a domain, is not supported.
- Use typed DTOs
  - use Address objects instead of string addresses
  - if the language supports it, use enums
- Return/throw custom errors (ValidationFailedError, ServerError, RateLimits)
- The SDK must be MIT-licensed.
- The SDK must use semantic versioning.
- The SDK must be published to the language's most prominent repository
  - Github Releases must initiate publishing automatically via Github actions
- Allow injecting a logger and HTTP client for testing, mocking, and debugging.
- In API requests, the `User-Agent` header must be set to `hyvor/relay-{language}/{version}` (ex: `hyvor/relay-php/1.0.0`)
- The SDK must have a README with a minimal example for sending an email.
- The SDK must have 100% code coverage, with Github Actions based CI.
