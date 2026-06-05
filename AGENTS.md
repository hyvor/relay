# Memory

## Project Overview
See @QWEN.md for the full architectural context. This is Hyvor Relay — self-hosted email API. Three components: backend (PHP/Symfony), worker (Go), frontend (SvelteKit).

## Code Style Guidelines
- Use descriptive variable names
- Follow existing patterns in the codebase
- Extract complex conditions into meaningful boolean variables

## Architecture Notes
**Hyvor Relay** is a multi-tenancy email delivery platform. 3 containers: PHP API (Symfony 7.4 + FrankenPHP), Go worker (concurrent SMTP pool + DNS + incoming SMTP), SvelteKit admin console. PostgreSQL for data + queues.

## Common Workflows
- Dev: `./run relay` → docker compose stack
- DB reset: `docker compose exec backend bin/console dev:reset --seed`
- Backend tests: `cd backend && php bin/phpunit`
- Worker tests: `cd worker && go test ./...`
- Frontend: `cd frontend && npm run dev`
- Deployment: `deploy/easy/` or `deploy/prod/`
- API: POST to `https://relay.hyvor.localhost/api/console/sends` w/ Bearer token
