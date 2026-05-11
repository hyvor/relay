<script lang="ts">
	import { IconMessage, LoadButton, Loader } from '@hyvor/design/components';
	import type { SudoSend, SudoSendRecipientStatus } from '../sudoTypes';
	import { getSends } from '../sudoActions';
	import SendRow from './SendRow.svelte';

	interface Props {
		project_id: number | null;
		status: SudoSendRecipientStatus | null;
		from_search?: string | null;
		to_search?: string | null;
		subject_search?: string | null;
		date_from_search?: string | null;
		date_to_search?: string | null;
		key: number;
	}

	let {
		project_id,
		status,
		from_search = null,
		to_search = null,
		subject_search = null,
		date_from_search = null,
		date_to_search = null,
		key = $bindable()
	}: Props = $props();

	const PER_PAGE = 25;

	let loading = $state(true);
	let loadingMore = $state(false);
	let hasMore = $state(true);
	let error: string | null = $state(null);
	let sends: SudoSend[] = $state([]);

	function load(more = false) {
		more ? (loadingMore = true) : (loading = true);

		getSends({
			project_id,
			status,
			from_search,
			to_search,
			subject_search,
			date_from_search,
			date_to_search,
			limit: PER_PAGE,
			before_id: more && sends.length > 0 ? sends[sends.length - 1].id : null
		})
			.then((data) => {
				sends = more ? [...sends, ...data] : data;
				hasMore = data.length === PER_PAGE;
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
		project_id;
		status;
		key;
		from_search;
		to_search;
		subject_search;
		date_from_search;
		date_to_search;
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
		<div class="header">
			<div>Project</div>
			<div>From</div>
			<div>Recipients</div>
			<div>Subject</div>
		</div>

		{#each sends as send (send.id)}
			<SendRow {send} />
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
		grid-template-columns: 1.5fr 2fr 3fr 2fr;
		font-size: 14px;
		font-weight: 600;
		color: var(--text-light);
		gap: 15px;
		padding: 5px 30px 15px;
	}
</style>
