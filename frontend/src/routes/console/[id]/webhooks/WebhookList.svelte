<script lang="ts">
	import WebhookRow from './WebhookRow.svelte';
	import type { Webhook } from '../../types';
	import { IconMessage } from '@hyvor/design/components';

	interface Props {
		webhooks: Webhook[];
		onEdit: (webhook: Webhook) => void;
		onDelete: (webhook: Webhook) => void;
	}

	let { webhooks, onEdit, onDelete }: Props = $props();
</script>

{#if webhooks.length === 0}
    <IconMessage empty size="large" message="No webhooks configured" />
{:else}
	<div class="webhooks-list">
		{#each webhooks as webhook (webhook.id)}
			<WebhookRow {webhook} {onEdit} {onDelete} />
		{/each}
	</div>
{/if}

<style>
	.webhooks-list {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
</style>
