<script lang="ts">
	import { Button, IconButton, Tag } from '@hyvor/design/components';
	import IconTrash from '@hyvor/icons/IconTrash';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import type { ApiKey } from '../../types';

	export let apiKey: ApiKey;
	export let onToggleEnabled: (apiKey: ApiKey) => void;
	export let onDelete: (apiKey: ApiKey) => void;

	const scopes = [
		{ value: 'send_email', label: 'Send Email' },
		{ value: 'full', label: 'Full Access' }
	];

	function getScopeLabel(scope: string) {
		return scopes.find(s => s.value === scope)?.label || scope;
	}
</script>

<div class="api-key-item">
	<div class="api-key-info">
		<div class="api-key-header">
			<h3>{apiKey.name}</h3>
			<div class="api-key-badges">
				<Tag>
					{getScopeLabel(apiKey.scope)}
				</Tag>
				<Tag color={apiKey.is_enabled ? 'green' : 'red'}>
					{apiKey.is_enabled ? 'Enabled' : 'Disabled'}
				</Tag>
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
		<Button
			size="small"
			variant={'fill-light'}
			color={apiKey.is_enabled ? 'red' : 'green'}
			on:click={() => onToggleEnabled(apiKey)}
		>
			{apiKey.is_enabled ? 'Disable' : 'Enable'}
		</Button>
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

	.api-key-header h3 {
		margin: 0;
		font-size: 16px;
		font-weight: 500;
		color: var(--text);
	}

	.api-key-badges {
		display: flex;
		gap: 8px;
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