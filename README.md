# Hyvor Relay

[Hyvor Relay](https://relay.hyvor.com) is a self-hosted, open-source email API for developers. It uses SMTP to send
emails using your own infrastructure. It is designed to be simple to self-host, easy to manage and observe, and powerful
enough to send millions of daily emails.

<p align="center">
  <a href="https://relay.hyvor.com">
    <img src="https://hyvor.com/img/logo.png" alt="Hyvor Relay Logo" width="130"/>
  </a>
</p>

<p align="center">
  <a href="https://relay.hyvor.com">
    Email API for Developers
  </a>
    <span> | </span>
    <a href="https://relay.hyvor.com/hosting">
    Self-Hosting Docs
  </a>
    <span> | </span>
    <a href="https://relay.hyvor.com/docs">
    Product Docs
  </a>
</p>

## Features

- **Self-Hosted**: Docker compose or swarm-based deployment.
- **Health Checks**: Multiple health checks to ensure best performance and deliverability.
- **Email API**: Send emails using a simple API.
- **Logs & SMTP Conversations**: View send logs and SMTP conversations of sent emails up to 30 days.
- **Multi-Tenancy**: Support for multiple tenants with scoped access (useful for organizations and agencies).
- **Project Management**: Support for multiple isolated projects within a tenant.
- **Queues**: Two main queues to isolate transactional and distributional emails and IP reputation.
- **Greylisting & Retries Handling**: Automatically manage greylisting and retries.
- **Bounce Handling**: Automatically handle bounced emails.
- **Feedback Loops**: Integrate with feedback loops to manage spam complaints.
- **Suppressions**: Automatically manage email suppressions (bounces, unsubscribes, etc.).
- **DNS Automation**: Delegate DNS to the in-built DNS server. No need to manage DNS records manually.
- **Webhooks**: Receive HTTP callbacks for email events.
- **Easy scaling**: Add more servers and IP addresses as needed.
- **Observability**: Prometheus metrics, Grafana dashboards, and logs for monitoring.

<!-- - **Dedicated IPs**: Support for dedicated IPs users. (coming soon) -->

## SDK

The following SDKs are available or planned:

| Language / Framework | Repository                                                    | Status |
|----------------------|---------------------------------------------------------------|--------|
| JavaScript*          | [hyvor/relay-js](https://github.com/hyvor/relay-js)           | WIP    |
| PHP                  | [hyvor/relay-php](https://github.com/hyvor/relay-php)         | N/A    |
| └── Symfony (Mailer) | [hyvor/relay-symfony](https://github.com/hyvor/relay-symfony) | N/A    |
| └── Laravel          | [hyvor/relay-laravel](https://github.com/hyvor/relay-laravel) | N/A    |
| Go                   | [hyvor/relay-go](https://github.com/hyvor/relay-go)           | N/A    |
| Ruby                 | [hyvor/relay-ruby](https://github.com/hyvor/relay-ruby)       | N/A    |
| Python               | [hyvor/relay-python](https://github.com/hyvor/relay-python)   | N/A    |
| Rust                 | [hyvor/relay-rust](https://github.com/hyvor/relay-rust)       | N/A    |
| Java                 | [hyvor/relay-java](https://github.com/hyvor/relay-java)       | N/A    |
| Dotnet               | [hyvor/relay-dotnet](https://github.com/hyvor/relay-dotnet)   | N/A    |

*JavaScript SDK is the primary implementation that other SDKs follow. See [sdk.md](./meta/playbooks/sdk.md) for rules for creating an official library.

## Screenshots

The sudo dashboard for admins:

![Sudo Dashboard](/meta/assets/screenshot-sudo.png)

The console for users (viewing send logs and SMTP conversations):

![User Console](/meta/assets/screenshot-console.png)

## Architecture

- **PHP + Symfony** for the API backend.
- **Go** for email workers, webhook handlers, DNS server, and the incoming SMTP server.
- **SvelteKit** and [**Hyvor Design System**](https://github.com/hyvor/design) for the frontend.
- **PGSQL** is used for the database as well as for the queue.

## Roadmap & Community

- [Roadmap](https://github.com/hyvor/relay/blob/main/ROADMAP.md)
- [HYVOR Community](https://hyvor.community)
- [Discord](https://hyvor.com/go/discord)

## Contributing

Visit [hyvor/dev](https://github.com/hyvor/dev) to set up the HYVOR development environment. Then, run `./run relay` to
start Hyvor Relay at `https://relay.hyvor.localhost`.

Directory structure:

- `/backend`: Symfony API backend
- `/frontend`: SvelteKit frontend
- `/worker`: Go services (single binary)

<!-- ## Performance TODO -->

## License

Hyvor Relay is licensed under the [AGPL-3.0 License](https://github.com/hyvor/relay/blob/main/LICENSE). We also offer [enterprise licenses](https://hyvor.com/enterprise) for organizations that require a commercial license or do not wish to comply with the AGPLv3 terms. See [Self-Hosting License FAQ](https://hyvor.com/docs/hosting-license) for more information.

![HYVOR Banner](https://raw.githubusercontent.com/hyvor/relay/refs/heads/main/meta/assets/hyvor-banner.svg)

Copyright © HYVOR. HYVOR name and logo are trademarks of HYVOR, SARL.
