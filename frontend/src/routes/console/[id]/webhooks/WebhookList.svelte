<script lang="ts">
	import WebhookRow from './WebhookRow.svelte';
	import type { Webhook } from '../../types';
	import { IconMessage } from '@hyvor/design/components';

	export let webhooks: Webhook[];
	export let loading: boolean;
	export let onEdit: (webhook: Webhook) => void;
	export let onDelete: (webhook: Webhook) => void;
</script>

{#if loading}
	<div class="loading">Loading...</div>
{:else if webhooks.length === 0}
    <IconMessage empty size="large" />
{:else}
	<div class="webhooks-list">
		{#each webhooks as webhook (webhook.id)}
			<WebhookRow 
				{webhook} 
				{onEdit} 
				{onDelete} 
			/>
		{/each}
	</div>
{/if}

<style>
	.loading {
		text-align: center;
		padding: 60px 20px;
		color: var(--text-light);
	}

	.webhooks-list {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
</style> 