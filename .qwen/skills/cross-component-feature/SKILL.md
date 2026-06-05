---
name: cross-component-feature
description: Implement cross-component features spanning Go worker and PHP backend in Hyvor Relay
source: auto-skill
extracted_at: '2026-06-05T07:44:23.390Z'
---

# Implementing Cross-Component Features (Go + PHP)

Pattern for adding features that require changes in both the Go worker and the PHP/Symfony backend in Hyvor Relay.

## Architecture Context

Hyvor Relay has two runtime components:
- **Go worker** — handles SMTP, DNS, metrics, webhooks. Reads state from PHP via `GET /api/local/state` (poll on startup) or `POST /state` (push on config change).
- **PHP backend** — Symfony API, config source of truth. Produces `GoState` JSON consumed by the worker.

State sync is **unidirectional**: PHP → Go. Config is owned by PHP/environment.

## General Workflow

### 1. Trace the Config Flow

Before writing code, trace how config gets from env var/DB to the Go runtime:

```
Env var → Config.php (Symfony #[Autowire]) → GoStateFactory::create()
→ GoState JSON → HTTP → Go worker state.go (GoState struct)
→ Component-specific Set() method (e.g., IncomingMailServer.Set())
→ Runtime behavior change
```

Check each link. The most common bug: adding a field to the Go struct but forgetting the PHP `GoState` class, or vice versa.

### 2. Go Worker Changes

- Add field to the relevant struct in `state.go` (e.g., `GoStateSecurity`)
- Update any `Set()` method that receives the parent struct
- Implement the runtime behavior change in the relevant file
- Add tests (unit tests on the Session/struct level, mocking globals like `CallConsoleSendApi`)

### 3. PHP Backend Changes

Three files to touch for new config values:

| File | Change |
|---|---|
| `backend/src/Service/App/Config.php` | Add `#[Autowire('%env(string:NAME)%')]` parameter + getter |
| `backend/src/Service/Management/GoState/GoState*.php` | Add property matching Go struct |
| `backend/src/Service/Management/GoState/GoStateFactory.php` | Populate from `$this->config` |

If a new PHP class is needed (e.g., `GoStateSecurity`), create it in `backend/src/Service/Management/GoState/`.

### 4. Env Var Documentation

- `backend/.env` — add commented-out defaults in "Defaults" section
- `worker/.env.example` — add worker-specific vars
- `deploy/easy/.env` and `deploy/prod/.env` — if user-facing

### 5. Env Var Conventions

- **Bool env vars**: use `%env(bool:NAME)%` in Symfony Autowire, default `false`
- **String env vars**: use `%env(string:NAME)%` in Symfony Autowire, default `''`
- **Comma-separated lists** (IPs, domains): store as string, parse with `array_map('trim', explode(',', $value))` in the getter
- **Secrets for the Go worker** (API keys, passwords): keep on the Go side via `os.Getenv("VAR_NAME")`, NOT in PHP state (secrets shouldn't cross the HTTP boundary unnecessarily)

### 6. Unit Test Pattern for Go SMTP Session

Unit test `Session` methods directly — no SMTP server needed:

```go
session := &Session{
    logger:  slogDiscard(),
    metrics: newMetrics(),
    security: GoStateSecurity{ ... },
    systemApiKey: "test-key",
    incomingMail: IncomingMail{
        RcptTo:         "recipient@other-domain.com",
        MailFrom:       "sender@example.com",
        InstanceDomain: "example.com",
    },
}

// Mock globals for API calls:
CallConsoleSendApi = func(ctx, apiKey, body) error {
    called = true
    return nil
}
```

Test matrix for SMTP path changes:
| Scenario | HasApiKey | AllowUnauthSending | RCPT domain | Expected path |
|---|---|---|---|---|
| Authenticated send | true | any | any | API |
| Unauth send to other domain | false | true | != instance | API (with system key) |
| Unauth send to instance | false | any | == instance | mailChannel (bounce/FBL) |
| Unauth send, no system key | false | true (no key) | != instance | mailChannel (dropped + warn) |
| Reject other domain | false | false | != instance | error at Rcpt() |

### 7. Modifying `Rcpt()` — Domain Check Pattern

The `Rcpt()` method has a gate that restricts unauthenticated sessions to the instance domain (for bounce/FBL handling). To add a new mode where some unauthenticated sends are accepted:

```go
if !s.incomingMail.HasApiKey() {
    domain := parsed.Address[atIndex+1:]

    // New mode: accept non-instance domains
    if s.security.AllowUnauthenticatedSending && domain != s.incomingMail.InstanceDomain {
        // allow — log and continue
    } else if domain != s.incomingMail.InstanceDomain {
        return errors.New("this SMTP server only accepts emails for " + s.incomingMail.InstanceDomain)
    }
}
```

Always keep the original error path for the default mode (preserving existing behavior).

### 8. Modifying `Data()` — Multi-Path Dispatch

The `Data()` method has three dispatch paths:

```
HasApiKey() → forwardEmailToApi (authenticated sends)
┐ AllowUnauthenticatedSending && systemApiKey != "" && domain != instance
  → set ApiKey = systemApiKey → forwardEmailToApi (unauthenticated sends)
┐ else → mailChannel <- mail (bounce/FBL path)
```

For unauthenticated sends, set the API key on the IncomingMail before calling `forwardEmailToApi`, allowing the existing API forwarding code to be reused without changes.

### 9. Security Considerations for SMTP Changes

- Unauthenticated SMTP sending is effectively an open relay — always pair with source IP allowlisting (`AllowedSourceIPs`) or sender domain allowlisting (`AllowedSenderDomains`)
- System API keys (for unauthenticated submissions) should be configured via env var on the Go worker, not embedded in the PHP state (secrets shouldn't cross the PHP→Go HTTP boundary unnecessarily)
- Instance domain emails should always fall through to bounce/FBL handling regardless of unauthenticated-sending flag
- Log a warning when the feature is enabled but the API key is missing, so the operator notices the misconfiguration
