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

Hyvor Relay's API is written in **PHP + Symfony**. The email workers, webhook handlers, DNS server, and the incoming SMTP server are written in **Go**. The frontend is built with **SvelteKit** and [**Hyvor Design System**](https://github.com/hyvor/design). **PGSQL** is used for the database. The queue also uses PGSQL with `LOCK FOR UPDATE`.

## Contributing

Visit [hyvor/dev](https://github.com/hyvor/dev) to set up HYVOR development environment. Run `./run relay` to start Hyvor Relay at `https://relay.hyvor.localhost`.
