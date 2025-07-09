<script lang="ts">
	import DomainRow from './DomainRow.svelte';
	import type { Domain } from '../../types';
	import { IconMessage, Loader } from '@hyvor/design/components';

	interface Props {
		domains: Domain[];
		loading: boolean;
		onDelete: (domain: Domain) => void;
		onVerify: (domain: Domain) => void;
	}

	let { domains, loading, onDelete, onVerify }: Props = $props();
</script>

{#if loading}
	<Loader />
{:else if domains.length === 0}
	<IconMessage empty size="large" />
{:else}
	<div class="domains-list">
		{#each domains as domain (domain.id)}
			<DomainRow {domain} {onDelete} {onVerify} />
		{/each}
	</div>
{/if}

<style>
	.domains-list {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
</style> 