<script lang="ts">
	import SuppressionRow from './SuppressionRow.svelte';
	import type { Suppression } from '../../types';
	import { IconMessage, Loader } from '@hyvor/design/components';

	interface Props {
		suppressions: Suppression[];
		loading: boolean;
		onDelete: (suppression: Suppression) => void;
	}

	let { suppressions, loading, onDelete }: Props = $props();
</script>

{#if loading}
	<Loader full />
{:else if suppressions.length === 0}
	<IconMessage empty message="No suppressions found" />
{:else}
	<div class="suppressions-list">
		{#each suppressions as suppression (suppression.id)}
			<SuppressionRow {suppression} {onDelete} />
		{/each}
	</div>
{/if}

<style>
	.suppressions-list {
		display: flex;
		flex-direction: column;
		gap: 1px;
		overflow: hidden;
	}
</style> 