# Hyvor Relay

[Hyvor Relay](https://relay.hyvor.com) is a self-hosted, open-source email API for developers. It is designed to be simple to self-host, easy to manage, and powerful enough to handle all your email needs.

<p align="center">
  <a href="https://relay.hyvor.com">
    <img src="https://hyvor.com/img/logo.png" alt="Hyvor Relay Logo" width="130"/>
  </a>
</p>

<p align="center">
  <a href="https://relay.hyvor.com">
    Website
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
- **Logging**: View logs of sent emails.
- **Handle Greylisting & Retries**: Automatically manage greylisting and retries.
- **Bounce Handling**: Automatically handle bounced emails.
- **Feedback Loops**: Integrate with feedback loops to manage complaints.
- **Suppressions**: Automatically manage email suppressions (bounces, unsubscribes, etc.).
- **DNS Automation**: Automatically manage DNS records for PTR, SPF, and DKIM.
- **Webhooks**: Receive HTTP callbacks for email events.
- **Health Checks**: Monitor the health of the service in the dashboard.
- **Easy scaling**: Add more servers and IP addresses as needed.
- **Observability**: Prometheus metrics, Grafana dashboards, and Loki logs for monitoring.

## Architecture

- The API is written in **PHP + Symfony**.
- The email workers, webhook handlers, DNS server, and the incoming SMTP server are written in **Go**.
- The frontend is built with **SvelteKit** and [**Hyvor Design System**](https://github.com/hyvor/design).
- **PGSQL** is used for the database as well as for the queue.

## Roadmap & Community

- [Roadmap](https://hyvor.com/roadmap)
- [HYVOR Community](https://hyvor.community) (Best for discussions and community support)
- [Discord](https://hyvor.com/discord) (Best for keeping up with the latest updates)

## Contributing

Visit [hyvor/dev](https://github.com/hyvor/dev) to set up HYVOR development environment. Then, run `./run relay` to start Hyvor Relay at `https://relay.hyvor.localhost`.

- `/backend`: Symfony API backend
- `/frontend`: SvelteKit frontend
- `/worker`: Go services (single binary)

Even though it is written for AI agents, [AGENTS.md](https://github.com/hyvor/relay/blob/readme/AGENTS.md) contains useful information on the project structure and development practices.

<!-- ## Performance

TODO -->

## License

Hyvor Relay is licensed under the [AGPL-3.0 License](https://github.com/hyvor/relay/blob/readme/LICENSE). AGPLv3 requires you to share the source code of your modifications if you run the software on a server and allow others to use it. For

[Enterprise License](https://hyvor.com/enterprise).

Copyright © HYVOR. HYVOR name and logo are trademarks of HYVOR.
