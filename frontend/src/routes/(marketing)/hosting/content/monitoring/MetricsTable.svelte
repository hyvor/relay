<script lang="ts">
	import { Button, Table, TableRow, Tag } from '@hyvor/design/components';

	interface Metric {
		name: string;
		labels?: string[];
		description?: string;
		global?: boolean;
	}

	let metrics: Metric[] = [
		// Global metrics (only exposed from Leader server)
		{
			name: 'relay_info',
			labels: ['version', 'env', 'instance_domain'],
			description: 'Information about the relay instance',
			global: true
		},
		{
			name: 'email_queue_size',
			labels: ['queue_name'],
			description: 'Number of pending emails in the queue',
			global: true
		},
		{
			name: 'pgsql_connections',
			labels: [],
			description: 'Number of active PostgreSQL connections',
			global: true
		},
		{
			name: 'pgsql_max_connections',
			labels: [],
			description: 'Maximum number of PostgreSQL connections',
			global: true
		},
		{
			name: 'servers_total',
			labels: [],
			description: 'Total number of registered servers in this Hyvor Relay instance',
			global: true
		},

		// Server (worker) metrics
		{
			name: 'email_send_attempts_total',
			labels: ['queue_name', 'ip', 'status'],
			description: 'Total number of email send attempts',
			global: false
		},
		{
			name: 'email_delivery_duration_seconds',
			labels: ['queue_name', 'ip'],
			description: 'Duration of email delivery in seconds',
			global: false
		},
		{
			name: 'workers_api_total',
			labels: [],
			description: 'Total number of API workers',
			global: false
		},
		{
			name: 'workers_email_total',
			labels: [],
			description: 'Total number of email workers',
			global: false
		},
		{
			name: 'workers_webhook_total',
			labels: [],
			description: 'Total number of webhook workers',
			global: false
		},
		{
			name: 'workers_incoming_mail_total',
			labels: [],
			description: 'Total number of incoming mail workers',
			global: false
		},
		{
			name: 'webhook_deliveries_total',
			labels: ['status'],
			description: 'Total number of webhook deliveries',
			global: false
		},
		{
			name: 'incoming_emails_total',
			labels: ['type'],
			description: 'Total number of incoming emails',
			global: false
		},
		{
			name: 'dns_queries_total',
			labels: ['type', 'status'],
			description: 'Total number of DNS queries handled',
			global: false
		},
		{
			name: 'http_requests_total',
			labels: ['method', 'endpoint', 'status'],
			description: 'Total number of HTTP requests handled'
		}
	];

	let showAll = $state(false);

	const filtered = $derived(showAll ? metrics : metrics.slice(0, 3));
</script>

<Table columns="3fr 2fr 3fr">
	<TableRow head>
		<div>Metric</div>
		<div>Labels</div>
		<div>Description</div>
	</TableRow>

	{#each filtered as { name, labels = [], description = '', global = false }}
		<TableRow>
			<div>
				<code>{name}</code>
			</div>
			<div class="labels">
				{#each labels as label}
					<code>{label}</code>
				{/each}
			</div>
			<div>
				{description}
				{#if global}
					<div style="margin-top:4px;">
						<Tag size="x-small" color="blue">Global</Tag>
					</div>
				{/if}
			</div>
		</TableRow>
	{/each}

	<div class="show-btn" class:show={showAll}>
		<Button size="small" color="input" onclick={() => (showAll = !showAll)}>
			{#if showAll}Show Less{:else}Show All{/if}
		</Button>
	</div>
</Table>

<style>
	.labels {
		display: flex;
		gap: 3px;
		flex-wrap: wrap;
	}

	div {
		word-break: break-all;
	}

	.show-btn {
		text-align: center;
		margin-top: 8px;
		position: relative;
	}

	.show-btn:not(.show):before {
		content: '';
		position: absolute;
		left: 0;
		width: 100%;
		bottom: 100%;
		height: 100px;
		background-image: linear-gradient(to bottom, rgba(0, 0, 0, 0), #fff);
	}
</style>
