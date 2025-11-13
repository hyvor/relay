<script lang="ts">
	import { IconMessage } from '@hyvor/design/components';
	import type { WebhookDelivery } from '../../types';
	import WebhookDeliveryRow from './WebhookDeliveryRow.svelte';

	interface Props {
		deliveries: WebhookDelivery[];
	}

	let { deliveries }: Props = $props();
</script>

{#if deliveries.length === 0}
	<IconMessage empty size="large" message="No webhook deliveries found" />
{:else}
	<div class="rows">
		{#each deliveries as delivery (delivery.id)}
			<WebhookDeliveryRow {delivery} />
		{/each}
	</div>
{/if}

<style>
	.rows {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
</style>
