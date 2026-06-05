---
name: project-analysis-qwen
description: Analyze a project directory and generate a comprehensive QWEN.md context file
source: auto-skill
extracted_at: '2026-06-05T07:19:50.646Z'
---

# Project Analysis for QWEN.md Generation

Systematic approach to analyze a project directory and produce a comprehensive `QWEN.md` file for future conversational context.

## Workflow

### 1. Initial Exploration
- List the root directory to get high-level structure
- Read `README.md` (or `README.txt`) — it's the best starting point

### 2. Iterative Deep Dive (up to ~10 files)

Let discoveries guide further reads. Target these categories:

**For code projects:**
- **Build configs**: `package.json`, `go.mod`/`Cargo.toml`, `composer.json`, `requirements.txt`, `Makefile`, `Dockerfile`, `compose.yaml`
- **Framework config**: `svelte.config.js`, `vite.config.ts`, `phpunit.dist.xml`, `.env` files
- **Source entry points**: `main.go`, `Kernel.php`, `app.py`, `index.ts`, etc.
- **Source tree structure**: key directories under `src/`, `app/`, etc.
- **Deployment**: `deploy/`, `Dockerfile` stages, compose files

**For non-code projects:**
- Index / TOC documents
- The most-linked or most-important files
- Any schema or metadata files

### 3. Identify Project Type

- **Code project**: look for `package.json`, `go.mod`, `Cargo.toml`, `requirements.txt`, `composer.json`, `Makefile`, `src/` dir, Dockerfile
- **Non-code project**: documentation, research, notes, data

### 4. Generate QWEN.md

**Structure for code projects:**
```
# Project Name — QWEN Context

## Project Overview
- One-paragraph summary
- Architecture table (component | language | role)
- Key tech stack highlights

## Source Tree
- Directory map with role annotations
- Key files and what they contain

## Building and Running
- Dev setup prerequisites
- Start / build commands
- Test commands for each component
- Lint / static analysis commands

## Development Conventions
- Coding style per component (PSR-12, go fmt, Prettier)
- Testing expectations
- Architectural patterns
```

### 5. Update AGENTS.md (if it exists)

Add a `QWEN.md` reference into the Project Overview section and populate Common Workflows with the most-used commands discovered.

## Pro Tips

- Always read `compose.yaml` + `Dockerfile` for dev workflow and build targets
- For Symfony projects: read `config/routes/routes.php` to map API surface
- For multi-component projects: each sub-project's config tells you its test/build commands
- `go test ./...` vs `php bin/phpunit` — capture the exact invocation
- Docker multi-stage builds reveal prod vs. dev targets
