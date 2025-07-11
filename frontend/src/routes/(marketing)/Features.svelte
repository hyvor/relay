<script>
	import Feature from './Feature.svelte';
</script>

<h3 id="features">Features</h3>

<div class="features">
	<Feature title="API for Emails">
		Send emails using a simple API. Supports HTML & plain text emails, attachments, custom
		headers, and more.
	</Feature>
	<Feature title="...and Everything Else">
		Manage domains, suppressions, and more using the API. Keys management, scopes & permissions,
		and idempotency are included.
	</Feature>
	<Feature title="Webhooks for Events">
		Receive notifications for email events like accepted, bounced, suppression added, and more.
	</Feature>
	<Feature title="Bounce & Complaint Handling">
		Hyvor Relay automatically handles bounces and complaints.
	</Feature>
	<Feature title="Email Logs">
		Detailed logs of sent emails including status, headers, SMTP conversations, and more. 30
		days of retention.
	</Feature>
</div>

<h3 id="features">Self-Hosting</h3>

<div class="features">
	<Feature title="Open-Source">
		Hyvor Relay is open-source and licensed under AGPL-3.0. It is built for easy self-hosting.
	</Feature>
	<Feature title="Health Checks">
		Monitor the health of your installation, IPs, and domains with built-in health checks.
	</Feature>
	<Feature title="Scalable">
		Desined to scale across multiple servers using Docker Swarm. Zero downtime scaling.
	</Feature>
	<Feature title="Deliverability">
		Deliverability is not an afterthought. Easy multiple IPs setup, queue separation, and
		dedicated IPs support.
	</Feature>
	<Feature title="In-built DNS Server">
		A simple DNS server to manage forward DNS, MX, SPF, DKIM records of your installation in
		highly dynamic environments.
	</Feature>
</div>

<style>
	h3 {
		font-size: 32px;
		line-height: 40px;
		margin-top: 40px;
		margin-bottom: 24px;
	}

	.features {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 24px;
	}
</style>
