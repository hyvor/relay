# Hyvor Relay — QWEN Context

## Project Overview

**Hyvor Relay** is a self-hosted, open-source email API for developers. It uses SMTP to send emails through the operator's own infrastructure and IPs. Designed for scalability — millions of emails per day.

### Architecture

Three-component hybrid architecture, all containerized via Docker:

| Component | Language/Framework | Role |
|---|---|---|
| **Backend** | PHP 8.4+ / Symfony 7.4 + Doctrine ORM | REST API, OIDC auth, DB migrations, business logic |
| **Worker** | Go 1.25 | Concurrent email sending pool, SMTP bounce capture, DNS authoritative server, webhook delivery, Prometheus metrics + OTEL tracing |
| **Frontend** | Svelte 5 / SvelteKit (static adapter) + TypeScript | Admin console UI, uses @hyvor/design system, Chart.js, dayjs |

**Router:** FrankenPHP (Caddy) serves both the PHP API and static frontend build.
**Database:** PostgreSQL (transactional queues via `FOR UPDATE SKIP LOCKED`).

### Source Tree

```
/root/relay/
├── backend/          # Symfony PHP app
│   ├── src/
│   │   ├── Api/      # Console, Local, Sudo, Root API controllers
│   │   ├── Command/  # CLI commands (dev reset, management, etc.)
│   │   ├── Entity/   # Doctrine entities (~24 entities)
│   │   ├── Service/  # Domain services (~31 subdirectories)
│   │   ├── Repository/
│   │   ├── Schedule/ # Symfony scheduler tasks
│   │   └── Kernel.php
│   ├── config/       # Symfony config (packages, routes, services)
│   ├── migrations/   # Doctrine migrations
│   ├── tests/        # PHPUnit tests
│   ├── composer.json
│   └── phpunit.dist.xml
├── worker/           # Go binary
│   ├── main.go           # Entry point (signal handling, OTEL init, HTTP server)
│   ├── state.go          # Service state w/ security policy (anti-open-relay)
│   ├── email_worker.go   # Concurrent email workers pool
│   ├── send.go           # SMTP send logic
│   ├── smtp/             # Low-level SMTP client (cmd.go, smtp.go + tests)
│   ├── incoming_server.go   # Incoming SMTP server (bounces, FBL, AUTH forwarding)
│   ├── incoming_handler.go  # Bounce/ARF parsing + dispatch
│   ├── http.go             # Local HTTP server (Symfony state push endpoint)
│   ├── pg.go               # PostgreSQL connection & queue polling
│   ├── send_pg.go          # Queue persistence
│   ├── webhooks.go         # Webhook delivery
│   ├── dns.go              # Authoritative DNS server for DKIM/SPF/DMARC
│   ├── mx.go               # MX resolution
│   ├── metrics.go          # Prometheus metrics
│   ├── tracing.go          # OpenTelemetry OTLP/HTTP tracing
│   ├── symfony_api.go      # HTTP client to Symfony backend
│   ├── bounceparse/        # DSN/ARF bounce parser library
│   └── smtp_interface/     # SMTP interface types
├── frontend/          # SvelteKit static SPA
│   ├── src/routes/    # Console, Sudo, marketing pages
│   ├── package.json   # Svelte 5, @hyvor/design, Chart.js
│   └── svelte.config.js # static adapter w/ fallback.html
├── deploy/            # Production deployment templates
│   ├── easy/          # All-in-one (PostgreSQL + Relay)
│   └── prod/          # External PostgreSQL
├── compose.yaml       # Dev Docker Compose stack
├── Dockerfile         # Multi-stage: frontend, worker, backend (dev + prod targets)
└── meta/              # Image assets, Caddy configs, supervisor configs
```

### Multi-Tenancy & API Surfaces

- **`/api/console/*`** — Tenant-facing CRUD (sends, domains, API keys, projects, webhooks, suppressions, analytics)
- **`/api/sudo/*`** — Super-admin operations
- **`/api/local/*`** — Internal communication between Go worker and Symfony (state push, SMTP auth delegation)
- **`/api/oidc/*`** — OpenID Connect authentication
- **`/api/root`** — Top-level root API

### Key Features

- Transactional & bulk priority queues (IP reputation isolation)
- Automatic bounce handling + FBL (ARF) parsing
- Automatic suppression list for hard bounces/spam complaints
- Authoritative DNS server (auto-delegates DKIM, SPF, DMARC)
- Anti-open-relay: source IP CIDR allowlist + sender domain allowlist
- SMTP AUTH delegation via Symfony (LDAP/AD support)
- Rate limiting per-API-key
- Webhook delivery with retries
- Prometheus metrics + Grafana dashboards (built-in)
- Distributed tracing via OpenTelemetry OTLP/HTTP (Go worker)

## Building and Running

### Development Setup

Prerequisites: Docker + Docker Compose + [hyvor/dev](https://github.com/hyvor/dev) (Traefik + Postgres).

```bash
# Start the full dev stack
./run relay

# Reset DB and seed with sample data
docker compose exec -it backend bash -c "bin/console dev:reset --seed"
```

### Docker Build Targets (Dockerfile)

| Target | Description |
|---|---|
| `frontend-dev` | Hot-reload SvelteKit dev server (port 80) |
| `frontend-prod` | Production static build |
| `worker-dev` | Go dev — runs `worker.dev.run` script |
| `worker` | Production Go binary build |
| `backend-dev` | Symfony dev with FrankenPHP + supervisor |
| `final` | Production multi-stage: PHP + static build + Go binary |

### Backend Commands (inside `backend` container)

```bash
# Run inside backend container:
bin/console dev:reset --seed          # Reset DB + seed sample data
bin/console doctrine:migrations:migrate  # Run pending migrations
bin/console <custom-command>
```

### Worker Commands

```bash
cd worker
go test ./... -coverprofile=coverage.out   # Run tests with coverage
go build -o ./worker .                     # Build binary
```

### Frontend Commands

```bash
cd frontend
npm run dev          # Vite dev server
npm run build        # Production build
npm run check        # Svelte-check type checking
npm run format       # Prettier format
npm run lint         # Prettier + ESLint
```

### Testing

**Backend (PHP):**
```bash
cd backend
composer phpstan     # PHPStan static analysis
php bin/phpunit      # PHPUnit tests (config: phpunit.dist.xml)
```

**Worker (Go):**
```bash
cd worker
go test ./... -coverprofile=coverage.out -v
go vet ./...
```

**Frontend (SvelteKit):**
```bash
cd frontend
npm run check        # svelte-check
npm run lint         # Prettier + ESLint
```

## Development Conventions

### General
- Mature project with production-level design patterns across all three stacks
- Multi-stage Docker builds for dev vs. prod
- Feature flags through state pushed from Symfony → Go worker HTTP endpoint

### PHP / Symfony
- PSR-12 coding style
- PHPStan level: strict (`phpstan.dist.neon`)
- PHPUnit with coverage metadata required (`requireCoverageMetadata="true"`)
- Doctrine entities with typed properties
- Attribute-based routing
- Separate API namespaces (Console, Sudo, Local, Root)
- Domain services organized in sub-namespaces under `Service/`

### Go
- Standard `go fmt` formatting
- `testing` package + testify for assertions
- Prometheus client for metrics, slog for structured logging
- OpenTelemetry tracing (no-op when OTEL endpoint unset)
- `sync.Mutex` for concurrent state management
- Clean separation: SMTP client library under `smtp/`, bounce parser under `bounceparse/`, interface types under `smtp_interface/`

### Svelte / TypeScript
- Static adapter (`@sveltejs/adapter-static`) w/ fallback for SPA routing
- Prettier + ESLint (flat config)
- `@hyvor/design` component library
- Centralized CSS custom properties in `src/routes/app.css`

### Infrastructure
- All services run in containers via Docker Compose / Docker Swarm
- Traefik reverse proxy in dev; Caddy/FrankenPHP in prod
- Postgres for persistent storage + queue
- Supervisor for process management inside backend container
- Environment config via `.env` files (backend) + `DATABASE_URL` parsing in Go
