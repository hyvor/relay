<script lang="ts">
	import APIKeyRow from './APIKeyRow.svelte';
	import type { ApiKey } from '../../types';
	import { IconMessage } from '@hyvor/design/components';

	export let apiKeys: ApiKey[];
	export let loading: boolean;
	export let onToggleEnabled: (apiKey: ApiKey) => void;
	export let onDelete: (apiKey: ApiKey) => void;
</script>

{#if loading}
	<div class="loading">Loading...</div>
{:else if apiKeys.length === 0}
	<IconMessage empty size="large" />
{:else}
	<div class="api-keys-list">
		{#each apiKeys as apiKey (apiKey.id)}
			<APIKeyRow {apiKey} {onToggleEnabled} {onDelete} />
		{/each}
	</div>
{/if}

<style>
	.loading {
		text-align: center;
		padding: 60px 20px;
		color: var(--text-light);
	}

	.api-keys-list {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
</style>
