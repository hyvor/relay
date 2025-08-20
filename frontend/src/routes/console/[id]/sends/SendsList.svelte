<script lang="ts">
	import { IconMessage, LoadButton, Loader } from '@hyvor/design/components';
	import type { Send, SendRecipientStatus } from '../../types';
	import { emailStore } from '../../lib/stores/projectStore.svelte';
	import { getSends } from '../../lib/actions/emailActions';
	import SendRow from './SendRow.svelte';

	interface Props {
		status: SendRecipientStatus | null;
		from_search?: string | null;
		to_search?: string | null;
		key: number; // just for forcing re-render
	}

	let { status, from_search = null, to_search = null, key = $bindable() }: Props = $props();

	let loading = $state(true);
	let hasMore = $state(true);
	let loadingMore = $state(false);
	let error: null | string = $state(null);

	const EMAILS_PER_PAGE = 25;

	let emails: Send[] = $state([]);

	function load(more = false) {
		more ? (loadingMore = true) : (loading = true);

		getSends(status, from_search, to_search, EMAILS_PER_PAGE, more ? emails.length : 0)
			.then((data) => {
				emails = more ? [...emails, ...data] : data;
				emailStore.set(emails);
				hasMore = data.length === EMAILS_PER_PAGE;
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
		status;
		key;
		from_search;
		to_search;

		load();
	});
</script>

{#if loading}
	<Loader full />
{:else if error}
	<IconMessage error message={error} />
{:else if emails.length === 0}
	<IconMessage empty message="No emails found" />
{:else}
	<div class="list">
		<div class="header">
			<div class="from">From</div>
			<div class="recipients">Recipients</div>
			<div class="subject">Subject</div>
			<div class="status">Status</div>
		</div>

		{#each emails as email (email.id)}
			<SendRow send={email} refreshList={() => (key += 1)} />
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
		grid-template-columns: 2fr 2fr 2fr 1fr;
		font-size: 14px;
		font-weight: 600;
		color: var(--text-light);
		gap: 10px;
		padding: 5px 30px 15px;
	}
</style>
