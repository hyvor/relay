<script lang="ts">
	import { Button, IconButton, Tag, Tooltip } from '@hyvor/design/components';
	import IconPencil from '@hyvor/icons/IconPencil';
	import IconTrash from '@hyvor/icons/IconTrash';
	import type { Webhook } from '../../types';

	export let webhook: Webhook;
	export let onEdit: (webhook: Webhook) => void;
	export let onDelete: (webhook: Webhook) => void;

	function getDisplayEvents(events: string[]): { visible: string[], remaining: string[] } {
		if (events.length <= 3) {
			return { visible: events, remaining: [] };
		}
		return {
			visible: events.slice(0, 3),
			remaining: events.slice(3)
		};
	}

	$: displayEvents = getDisplayEvents(webhook.events);
</script>

<div class="webhook-row">
	<div class="webhook-info">
		<div class="webhook-header">
			<h3 class="webhook-url">{webhook.url}</h3>
			<div class="webhook-events">
				{#if webhook.events.length === 0}
					<Tag variant="gray" size="small">No events</Tag>
				{:else}
					{#each displayEvents.visible as event}
						<Tag variant="gray" size="small">{event}</Tag>
					{/each}
					{#if displayEvents.remaining.length > 0}
						<Tooltip text={displayEvents.remaining.join(', ')}>
							<Tag variant="gray" size="small">+{displayEvents.remaining.length} more</Tag>
						</Tooltip>
					{/if}
				{/if}
			</div>
		</div>
		{#if webhook.description}
			Description: <p class="webhook-description">{webhook.description}</p>
		{/if}
	</div>
	
	<div class="webhook-actions">
		<IconButton
            variant="fill-light"
            color="accent"
            size="small"
			on:click={() => onEdit(webhook)}
		>
			<IconPencil size={12} />
		</IconButton>
		<IconButton
            variant="fill-light"
            color="red"
            size="small"
			on:click={() => onDelete(webhook)}
		>
			<IconTrash size={12} />
		</IconButton>
	</div>
</div>

<style>
	.webhook-row {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		padding: 20px;
		gap: 20px;
	}

	.webhook-info {
		flex: 1;
		min-width: 0;
	}

	.webhook-header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 8px;
	}

	.webhook-url {
		font-size: 16px;
		font-weight: 600;
		margin: 0;
		color: var(--text);
		word-break: break-all;
	}

	.webhook-events {
		flex-shrink: 0;
		display: flex;
		gap: 6px;
		flex-wrap: wrap;
	}

	.webhook-description {
		margin: 0;
		font-size: 14px;
		color: var(--text-light);
		line-height: 1.4;
	}

	.webhook-actions {
		display: flex;
		gap: 8px;
		flex-shrink: 0;
	}

	@media (max-width: 640px) {
		.webhook-row {
			flex-direction: column;
			align-items: stretch;
		}

		.webhook-actions {
			justify-content: flex-end;
		}
	}
</style> 