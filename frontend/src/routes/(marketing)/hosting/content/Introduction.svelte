<script>
	import { Divider } from '@hyvor/design/components';
</script>

<h1>Hosting</h1>

<p>
	<a href="/">Hyvor Relay</a> is a self-hosted, open-source email API for developers. Think of it as
	an alternative to AWS SES, Mailgun, or SendGrid, but you host it on your own infrastructure.
</p>

<h2>Self-Hosting is first-class</h2>

<p>Hyvor Relay is designed to be self-hosted easily.</p>

<ul>
	<li>
		<strong>Open Source</strong>: Fully open-source codebase available on
		<a href="https://github.com/hyvor/relay" target="_blank">GitHub</a>. All features are (and
		will remain) available in the open-source version.
	</li>
	<li>
		<strong>Minimal Dependencies</strong>: Easy docker-based deployments with just PostgreSQL as
		a dependency.
	</li>
	<li>
		<strong> Automation </strong>: Automatic server registration, IP address configuration, and
		health checks. In-built DNS server for DNS automation.
	</li>
	<li>
		<strong> Self-contained </strong>: Everything (email workers, bounce SMTP server, webhooks,
		DNS) is built into a single docker image.
	</li>
	<li>
		<strong> Dashboards for ease </strong>: Web interfaces for admins and users to ease setup
		and debugging.
	</li>
	<li>
		<strong> Industry-grade Monitoring </strong>: Prometheus, Grafana, Altermanager integrations
		are built-in for observability.
	</li>
</ul>

<p>
	Get started: <a href="/hosting/deploy-easy">Easy Deploy</a> (single server for small to medium
	sending volumes) or <a href="/hosting/deploy">Prod Deploy</a> (multiple servers for large sending
	volumes).
</p>

<h2 id="license">License</h2>

<p>
	Hyvor Relay is licensed under the <strong>AGPL-3.0 License</strong>. We also offer
	<a href="https://hyvor.com/enterprise" target="_blank">enterprise licenses</a>
	for organizations that require a commercial license or do not wish to comply with the AGPLv3 terms.
</p>
<p>
	See HYVOR's
	<a href="https://hyvor.com/docs/hosting-license" target="_blank">Self-Hosting License FAQ</a> for
	more information.
</p>

<h2 id="security">Security</h2>

<h3>DDoS</h3>

<p>
	<strong> Layer 7 </strong>: Hyvor Relay has built-in rate limiting to prevent DDoS attacks at
	the application layer for the HTTP server.
</p>

<p>
	<strong>Layer 3/4</strong>: You need to ensure that your server is protected against DDoS
	attacks at the network layer. This is usually managed by your cloud provider.
</p>

<Divider />

<p>
	Report security issues to <a href="mailto:security@hyvor.com">security@hyvor.com</a> (<a
		href="https://hyvor.com/gpg.txt">PGP Key</a
	>).
</p>
