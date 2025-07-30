<script>
	import { Button } from '@hyvor/design/components';
</script>

<h1>Hosting Hyvor Relay</h1>

<p>
	Hyvor Relay is a self-hostable, open-source email API for developers. Think of it as an
	alternative to AWS SES, Mailgun, or SendGrid, but you self-host it on your own servers.
</p>

<h2>Self-Hosting is first-class</h2>

<p>Hyvor Relay is designed to be self-hosted easily.</p>

<ul>
	<li>
		<strong>Minimal Dependencies</strong>: Easy docker-based deployments with just PostgreSQL as
		a dependency.
	</li>
	<li>
		<strong> Automation </strong>: Automatic server registration, IP address configuration, and
		health checks. In-built DNS server for DNS automation.
	</li>
	<li>
		<strong> Self-contained </strong>: Everything (email queues, bounce SMTP server, webhooks,
		DNS) is built into a single docker image.
	</li>
	<li>
		<strong> Prefer dashboards over config files </strong>: Opinionated towards using a web
		interface for most of the configurations instead of config files.
	</li>
	<li>
		<strong> Industry-grade Monitoring </strong>: Prometheus, Grafana, and Loki are built-in for
		<a href="/docs/monitoring">monitoring</a>, logging, and alerting.
	</li>
</ul>

<p style="margin: 30px 0;" class="button">
	<Button as="a" href="/hosting/deploy">Deploy Now</Button>
</p>

<h2>Security</h2>

<h3>DDoS</h3>

<p>
	<strong> Layer 7 </strong>: Hyvor Relay has built-in rate limiting and IP blocking to prevent
	DDoS attacks at the application layer for the HTTP, DNS, and SMTP servers.
</p>

<p>
	<strong>Layer 3/4</strong>: You need to ensure that your server is protected against DDoS
	attacks at the network layer. This is usually managed by your cloud provider.
</p>

<style>
	.button :global(a) {
		text-decoration: none !important;
	}
</style>
