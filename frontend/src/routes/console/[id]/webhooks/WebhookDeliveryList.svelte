<script lang="ts">
	import { Tag, IconMessage, Table, TableRow, TableCell } from '@hyvor/design/components';
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

{#if deliveries.length === 0}
	<IconMessage empty size="large" message="No webhook deliveries found" />
{:else}
	<Table columns="2fr 1fr 1fr 1fr" hover>
		<TableRow head>
			<TableCell>URL</TableCell>
			<TableCell>Event</TableCell>
			<TableCell>Status</TableCell>
			<TableCell>Created</TableCell>
		</TableRow>
		{#each deliveries as delivery (delivery.id)}
			<TableRow>
				<TableCell>
					{truncateUrl(delivery.url)}
				</TableCell>
				<TableCell>
					<Tag variant="gray" size="small">{delivery.event}</Tag>
				</TableCell>
				<TableCell>
					<Tag color={getStatusColor(delivery.status)} size="small">
						{delivery.status}
					</Tag>
				</TableCell>
				<TableCell>
					<RelativeTime unix={delivery.created_at} />
				</TableCell>
			</TableRow>
		{/each}
	</Table>
{/if}

<style>
</style> 