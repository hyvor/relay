<script>
	import { CodeBlock, Table, TableRow } from '@hyvor/design/components';

	// Update this if telemetry payload is updated
	const payload = {
		uuid: 'A unique identifier for your instance',
		version: 'Hyvor Relay version',

		// infra
		servers_count: 'Number of servers configured',
		ip_addresses_count: 'Number of IP addresses configured',
		workers_api_count: 'Number of API workers on all servers',
		workers_email_count: 'Number of email (SMTP) workers on all servers',
		workers_incoming_count: 'Number of incoming mail workers on all servers',
		workers_webhook_count: 'Number of Webhook workers on all servers',

		// users & projects
		users_count: 'Number of users (OIDC)',
		projects_count: 'Number of projects',
		domains_count: 'Number of domains configured',
		domains_verified_count: 'Number of verified domains',

		// sends
		sends_24h_count: 'Number of email sends in the last 24 hours',
		recipients_24h_accepted_count: 'Number of accepted email recipients in the last 24 hours',
		recipients_24h_bounced_count: 'Number of bounced email recipients in the last 24 hours',
		recipients_24h_complained_count:
			'Number of complained email recipients in the last 24 hours',
		recipients_24h_failed_count: 'Number of failed email recipients in the last 24 hours',

		// webhooks
		webhooks_delivered_24h_count: 'Number of webhooks delivered in the last 24 hours'
	};
</script>

<h1>Telemetry</h1>

<p>
	Hyvor Relay collects <strong>anonymous telemetry</strong> data to monitor basic usage patterns and
	performance metrics. This data helps us improve the service and identify potential issues.
</p>

<h2 id="disabling-telemetry">Disabling Telemetry</h2>

<p>You can disable telemetry by setting an <a href="/hosting/env">environment variable</a>:</p>

<CodeBlock code={`TELEMETRY=false`} language="yaml" />

<h2 id="data-collected">Data Collected</h2>

<p>
	When telemetry is enabled, we send a request from your Relay instance to our servers at <strong
		>hyvor.com</strong
	> once every 24 hours. The payload includes the following information:
</p>

<Table columns="1fr 1fr">
	<TableRow head>
		<div>Key</div>
		<div>Description</div>
	</TableRow>

	{#each Object.entries(payload) as [key, value]}
		<TableRow>
			<div><code>{key}</code></div>
			<div>{value}</div>
		</TableRow>
	{/each}
</Table>
