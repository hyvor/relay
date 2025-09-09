# Hyvor Relay

[Hyvor Relay](https://relay.hyvor.com) is a self-hosted, open-source email API for developers. It is designed to be simple to self-host, easy to manage and observe, and powerful enough to send millions of emails per day.

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
- **Email API**: Send emails using a simple API.
- **Logs & SMTP Conversations**: View logs and SMTP conversations of sent emails up to 30 days.
- **Multi-Tenancy**: Support for multiple tenants with scoped access (useful for organizations and agencies).
- **Project Management**: Support for multiple isolated projects within a tenant.
- **Queues**: Two main queues to isolate transactional and distributional emails and IP reputation.
- **Greylisting & Retries Handling**: Automatically manage greylisting and retries.
- **Bounce Handling**: Automatically handle bounced emails.
- **Feedback Loops**: Integrate with feedback loops to manage spam complaints.
- **Suppressions**: Automatically manage email suppressions (bounces, unsubscribes, etc.).
- **DNS Automation**: Delegate DNS to the in-built DNS server. No need to manage DNS records manually.
- **Webhooks**: Receive HTTP callbacks for email events.
- **Health Checks**: Monitor the health of the service in the dashboard.
- **Easy scaling**: Add more servers and IP addresses as needed.
- **Observability**: Prometheus metrics, Grafana dashboards, and logs for monitoring.
<!-- - **Dedicated IPs**: Support for dedicated IPs users. (coming soon) -->

## Architecture

- **PHP + Symfony** for the API backend.
- **Go** for email workers, webhook handlers, DNS server, and the incoming SMTP server.
- **SvelteKit** and [**Hyvor Design System**](https://github.com/hyvor/design) for the frontend.
- **PGSQL** is used for the database as well as for the queue.

## Roadmap & Community

- [Roadmap](https://github.com/hyvor/relay/blob/main/ROADMAP.md)
- [HYVOR Community](https://hyvor.community) (best for discussions and support)
- [Discord](https://hyvor.com/discord) (best for latest updates)

## Contributing

Visit [hyvor/dev](https://github.com/hyvor/dev) to set up the HYVOR development environment. Then, run `./run relay` to start Hyvor Relay at `https://relay.hyvor.localhost`.

Directory structure:

- `/backend`: Symfony API backend
- `/frontend`: SvelteKit frontend
- `/worker`: Go services (single binary)

Even though it is written for AI agents, [AGENTS.md](https://github.com/hyvor/relay/blob/main/AGENTS.md) contains useful information on the project structure and development practices.

<!-- ## Performance TODO -->

## License

Hyvor Relay is licensed under the [AGPL-3.0 License](https://github.com/hyvor/relay/blob/main/LICENSE). For use cases that cannot comply with AGPLv3, contact HYVOR for an [Enterprise License](https://hyvor.com/enterprise).

![HYVOR Banner](https://raw.githubusercontent.com/hyvor/relay/refs/heads/main/meta/assets/hyvor-banner.svg)

Copyright © HYVOR. HYVOR name and logo are trademarks of HYVOR, SARL.
