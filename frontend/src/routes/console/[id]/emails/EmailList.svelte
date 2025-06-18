<script lang="ts">
	import { IconMessage, LoadButton, Loader } from '@hyvor/design/components';
	import type { Email, EmailStatus } from '../../types';
	import { emailStore } from '../../lib/stores/projectStore';
	import { getEmails } from '../../lib/actions/emailActions';
	import EmailRow from './EmailRow.svelte';

	interface Props {
		status: EmailStatus | null;
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

	let emails: Email[] = $state([]);

	function load(more = false) {
		more ? (loadingMore = true) : (loading = true);

		getEmails(status, from_search, to_search, EMAILS_PER_PAGE, more ? emails.length : 0)
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
		{#each emails as email (email.id)}
			<EmailRow {email} refreshList={() => (key += 1)} />
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
		padding: 20px 30px;
	}
</style>
