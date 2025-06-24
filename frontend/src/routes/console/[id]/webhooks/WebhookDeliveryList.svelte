<script lang="ts">
	import { Tag, IconMessage } from '@hyvor/design/components';
	import type { WebhookDelivery } from '../../types';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';

	export let deliveries: WebhookDelivery[];
	export let loading: boolean;

	function getStatusColor(status: string) {
		switch (status) {
			case 'delivered':
				return 'green';
			case 'failed':
				return 'red';
			case 'pending':
				return 'orange';
			default:
				return 'default';
		}
	}

	function truncateUrl(url: string, maxLength: number = 150) {
		if (url.length <= maxLength) return url;
		return url.substring(0, maxLength) + '...';
	}
</script>

{#if loading}
	<div class="loading">Loading deliveries...</div>
{:else if deliveries.length === 0}
	<IconMessage empty size="large" message="No webhook deliveries found" />
{:else}
	<div class="deliveries-table">
		<table>
			<thead>
				<tr>
					<th>URL</th>
					<th>Event</th>
					<th>Status</th>
					<th>Created</th>
				</tr>
			</thead>
			<tbody>
				{#each deliveries as delivery (delivery.id)}
					<tr>
						<td>
							{truncateUrl(delivery.url)}
						</td>
						<td>
							<Tag variant="gray" size="small">{delivery.event}</Tag>
						</td>
						<td>
							<Tag color={getStatusColor(delivery.status)} size="small">
								{delivery.status}
							</Tag>
						</td>
						<td>
							<RelativeTime unix={delivery.created_at} />
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	</div>
{/if}

<style>
	.loading {
		text-align: center;
		padding: 60px 20px;
		color: var(--text-light);
	}

	.deliveries-table {
		overflow-x: auto;
	}

	table {
		width: 100%;
		border-collapse: collapse;
		font-size: 14px;
	}

	th,
	td {
		padding: 12px 16px;
		text-align: left;
		border-bottom: 1px solid var(--border);
	}

	th {
		font-weight: 600;
		color: var(--text-light);
		background-color: var(--background);
		font-size: 13px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	tbody tr:hover {
		background-color: var(--background);
	}
</style> 