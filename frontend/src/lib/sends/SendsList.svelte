<script lang="ts">
	import { IconMessage, LoadButton, Loader } from '@hyvor/design/components';
	import type { Send } from '../../routes/console/types';
	import SendRow from './SendRow.svelte';

	interface Props {
		fetchSends: (beforeId: number | null) => Promise<Send[]>;
		hrefBuilder: (send: Send) => string;
		showProject?: boolean;
		/**
		 * Changes whenever a reload is required. Parent encodes filter
		 * state (and any nonce for forced refreshes) into this value.
		 */
		queryKey: string | number;
		perPage?: number;
	}

	let {
		fetchSends,
		hrefBuilder,
		showProject = false,
		queryKey,
		perPage = 25
	}: Props = $props();

	let loading = $state(true);
	let hasMore = $state(true);
	let loadingMore = $state(false);
	let error: null | string = $state(null);

	let sends: Send[] = $state([]);

	function load(more = false) {
		more ? (loadingMore = true) : (loading = true);

		fetchSends(more && sends.length > 0 ? sends[sends.length - 1].id : null)
			.then((data) => {
				sends = more ? [...sends, ...data] : data;
				hasMore = data.length === perPage;
			})
			.catch((e) => {
				error = e.message;
			})
			.finally(() => {
				loading = false;
				loadingMore = false;
			});
	}

	$effect(() => {
		queryKey;
		load();
	});
</script>

{#if loading}
	<Loader full />
{:else if error}
	<IconMessage error message={error} />
{:else if sends.length === 0}
	<IconMessage empty message="No sends found" />
{:else}
	<div class="list">
		<div class="header" class:with-project={showProject}>
			{#if showProject}
				<div>Project</div>
			{/if}
			<div>From</div>
			<div>Recipients</div>
			<div>Subject</div>
		</div>

		{#each sends as send (send.id)}
			<SendRow {send} {hrefBuilder} {showProject} />
		{/each}
		<LoadButton
			text="Load More"
			loading={loadingMore}
			show={hasMore}
			on:click={() => load(true)}
		/>
	</div>
{/if}

<style>
	.list {
		flex: 1;
		overflow: auto;
		padding: 20px 0px;
	}

	.header {
		display: grid;
		grid-template-columns: 2fr 3fr 2fr;
		font-size: 14px;
		font-weight: 600;
		color: var(--text-light);
		gap: 15px;
		padding: 5px 30px 15px;
	}

	.header.with-project {
		grid-template-columns: 1.5fr 2fr 3fr 2fr;
	}
</style>
