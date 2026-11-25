# Docs

Source of truth for the Hyvor Relay documentation:

- `docs/` - [hyvor.com/relay/docs](https://hyvor.com/relay/docs)
- `hosting/` - [hyvor.com/relay/hosting](https://hyvor.com/relay/hosting)

These directories are synced to [hyvor/marketing](https://github.com/hyvor/marketing), which renders them (see "Product docs" in its README). The sync is triggered by `.github/workflows/sync-docs-to-marketing.yaml` when `docs/**` changes on `main`.

For local testing, `frontend` renders the same files at `/docs` and `/hosting` (via the `$docs` alias).

## Structure

```
<section>/
    <section>.ts     # exports getSections(lang), the navigation
    en/*.md          # English pages (Markdown with Svelte support)
    locale/en.json   # navigation labels
    images/          # images, imported relatively from the pages
    components/      # Svelte components used by the pages
```

Only English is available at the moment.

## Rules

Each section is copied as-is to another repo, so it must be self-contained:

- Only import from within this directory, `svelte`, `@hyvor/design`, and `@hyvor/icons`.
- Link to other docs pages with root-relative paths as they are served in this repo (`/docs/domains`, `/hosting/env`). The sync prefixes them with the product path (`/relay/docs/domains`).
- Link to marketing pages with absolute URLs (`https://hyvor.com/relay/...`), and to the app with `https://relay.hyvor.com/console`.
