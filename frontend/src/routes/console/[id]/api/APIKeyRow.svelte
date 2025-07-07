<script lang="ts">
	import { IconButton, Tag, Tooltip } from '@hyvor/design/components';
	import IconTrash from '@hyvor/icons/IconTrash';
	import IconPencil from '@hyvor/icons/IconPencil';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import type { ApiKey } from '../../types';

	interface Props {
		apiKey: ApiKey;
		onDelete: (apiKey: ApiKey) => void;
		onEdit: (apiKey: ApiKey) => void;
	}

	let { apiKey, onDelete, onEdit }: Props = $props();

	function getDisplayScopes(scopes: string[]): { visible: string[], remaining: string[] } {
		if (scopes.length <= 2) {
			return { visible: scopes, remaining: [] };
		}
		return {
			visible: scopes.slice(0, 2),
			remaining: scopes.slice(2)
		};
	}

	const displayScopes = $derived(getDisplayScopes(apiKey.scopes));

</script>

<div class="api-key-item">
	<div class="api-key-info">
		<div class="api-key-header">
			{apiKey.name}
			<div class="api-key-badges">
				<Tag color={apiKey.is_enabled ? 'green' : 'red'}>
					{apiKey.is_enabled ? 'Enabled' : 'Disabled'}
				</Tag>
				<div class="scopes-tags">
					{#if apiKey.scopes.length === 0}
						<Tag size="small" variant="gray">No scopes</Tag>
					{:else}
						{#each displayScopes.visible as scope}
							<Tag size="small">
								{scope}
							</Tag>
						{/each}
						{#if displayScopes.remaining.length > 0}
							<Tooltip text={displayScopes.remaining.join(', ')}>
								<Tag size="small">+{displayScopes.remaining.length} more</Tag>
							</Tooltip>
						{/if}
					{/if}
				</div>
	
			</div>
		</div>
		<div class="api-key-meta">
			<span>Created: <RelativeTime unix={apiKey.created_at} /></span>
			{#if apiKey.last_accessed_at}
				<span>Last used: <RelativeTime unix={apiKey.last_accessed_at} /></span>
			{:else}
				<span>Never used</span>
			{/if}
		</div>
	</div>
	<div class="api-key-actions">
		<IconButton
			variant="fill-light"
			size="small"
			on:click={() => onEdit(apiKey)}
		>
			<IconPencil size={12} />
		</IconButton>
		<IconButton
			variant="fill-light"
			color="red"
            size="small"
			on:click={() => onDelete(apiKey)}
		>
			<IconTrash size={12} />
		</IconButton>
	</div>
</div>

<style>
	.api-key-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20px;
	}

	.api-key-info {
		flex: 1;
	}

	.api-key-header {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 8px;
	}

	.api-key-badges {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	.scopes-tags {
		display: flex;
		gap: 4px;
		flex-wrap: wrap;
	}

	.api-key-meta {
		display: flex;
		gap: 16px;
		font-size: 14px;
		color: var(--text-light);
	}

	.api-key-actions {
		display: flex;
		align-items: center;
		gap: 12px;
	}
</style> 