<script>
	import { Divider } from '@hyvor/design/components';
	import { DocsImage } from '@hyvor/design/marketing';
</script>

<h1>Hosting</h1>

<p>
	<a href="/">Hyvor Relay</a> is a self-hosted, open-source email API for developers. Think of it as
	an alternative to AWS SES, Mailgun, or SendGrid, but you host it on your own infrastructure.
</p>

<h2 id="architecture">Architecture</h2>

<p>
	Hyvor Relay is a combination of several components that work together to provide a complete
	email sending solution:
</p>

<ul>
	<li>
		<strong>API server</strong>: Responsible for working as an interface between the end-user
		applications and the Hyvor Relay system. It exposes RESTful endpoints for sending emails,
		managing domains, etc.
	</li>
	<li>
		<strong>Email Workers</strong>: Responsible for sending emails via SMTP to recipient mail
		servers through pre-configured queues and IP addresses.
	</li>
	<li>
		<strong>SMTP Server</strong>: Handles incoming emails such as bounces, feedback loops, and
		also used as a compatibility layer for sending emails via SMTP.
	</li>
	<li>
		<strong>DNS Server</strong>: Manages DNS records for the instance domain, automating DKIM,
		SPF, PTR, and other DNS records required for SMTP & TLS.
	</li>
	<li>
		<strong>Webhook Workers</strong>: Responsible for sending webhooks to user-defined
		endpoints.
	</li>
</ul>

<p>Here is a high-level architecture diagram of Hyvor Relay (click to enlarge):</p>

<DocsImage src="/img/docs/intro-arch.png" alt="Hyvor Relay Architecture" />

<h2 id="what-to-expect">What to expect</h2>

<p>Here's a very short summary of what to expect when self-hosting Hyvor Relay:</p>

<ul>
	<li>
		First, you would rent/buy one or more <strong>servers</strong> from a cloud provider of your
		choice. One is enough in most cases, but you can use multiple servers for high availability and
		scalability.
	</li>
	<li>
		Each server would have one or more <strong>public IPv4 addresses</strong> assigned to it. Hyvor
		Relay will choose these IPs for sending emails based on the queue they belong to (which you can
		configure). They will also be used for the built-in SMTP, DNS, and API servers.
	</li>
	<li>
		You would then also need a <strong>PostgreSQL database</strong> server. This can be a self-hosted
		database or a managed database service from your cloud provider.
	</li>
	<li>
		Then, you would deploy Hyvor Relay on the app servers using <strong>Docker Compose</strong>
		or
		<strong>Docker Swarm</strong>. Hyvor Relay will run on the host network to have direct
		access to the public IP addresses.
	</li>
	<li>
		Finally, you would need to <strong>configure DNS records</strong> to point the web domain to
		Hyvor Relay HTTP server. Then, you would also delegate DNS management of your instance domain
		to Hyvor Relay DNS server by updating the NS records. We discuss the web and instance domains
		in detail in the next pages.
	</li>
</ul>

<h2 id="self-hosting">Self-Hosting is first-class</h2>

<p>
	Hyvor Relay is designed to be self-hosted easily for developers and organizations that want full
	control over their email sending infrastructure.
</p>

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
		<strong>Health Checks</strong>: Built-in health checks for all components to ensure smooth
		operation.
	</li>
	<li>
		<strong>Scalability</strong>: Designed to scale horizontally by adding more servers and
		workers.
	</li>
	<li>
		<strong> Industry-grade Monitoring </strong>: Prometheus, Grafana, Altermanager integrations
		are built-in for observability.
	</li>
</ul>

<p>
	Get started: <a href="/hosting/deploy-easy">Easy Deploy</a> (single server for small to medium
	sending volumes) or <a href="/hosting/deploy">Prod Deploy</a> (multiple servers for large sending
	volumes and high availability).
</p>

<h2 id="license">License</h2>

<p>
	Hyvor Relay is licensed under the <strong>AGPL-3.0 License</strong>. We also offer
	<a href="https://hyvor.com/enterprise" target="_blank">enterprise licenses</a>
	for organizations that require a commercial license or do not wish to comply with the AGPLv3 terms.
	Both licenses include the same features.
</p>
<p>
	See HYVOR's
	<a href="https://hyvor.com/docs/hosting-license" target="_blank">Self-Hosting License FAQ</a> for
	more information.
</p>

<h2 id="security">Security</h2>

<h3>DDoS</h3>

<p>
	<strong> Layer 7 </strong>: Hyvor Relay has built-in rate limiting to mitigate DDoS attacks at
	the application layer for the HTTP server.
</p>

<p>
	<strong>Layer 3/4</strong>: You need to ensure that your server is protected against DDoS
	attacks at the network layer. This is usually managed by your cloud provider. Since Hyvor Relay
	also exposes SMTP and DNS servers, it is highly recommended to use a cloud provider that offers
	strong DDoS protection.
</p>

<Divider />

<p>
	Report security issues to <a href="mailto:security@hyvor.com">security@hyvor.com</a> (<a
		href="https://hyvor.com/gpg.txt">PGP Key</a
	>).
</p>
