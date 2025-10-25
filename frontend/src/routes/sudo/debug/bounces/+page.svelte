<script lang="ts">
	import { onMount } from 'svelte';
	import type { DebugIncomingEmail } from '../../sudoTypes';
	import { debugGetIncomingMails } from '../../sudoActions';
	import { IconMessage, toast, Button, LoadButton } from '@hyvor/design/components';
	import BounceRow from './BounceRow.svelte';

	let mails: DebugIncomingEmail[] = [];
	let offset = 0;
	let loading = false;
	let hasMore = true;
	const limit = 20;

	function loadMails(more = false) {
		if (loading) return;

		loading = true;
		const currentOffset = more ? offset : 0;

		debugGetIncomingMails(limit, currentOffset)
			.then((data) => {
				if (more) {
					mails = [...mails, ...data];
				} else {
					mails = data;
					offset = 0;
				}
				offset = currentOffset + data.length;
				hasMore = data.length === limit;
			})
			.catch((error) => {
				toast.error(error.message);
			})
			.finally(() => {
				loading = false;
			});
	}

	function loadMore() {
		loadMails(true);
	}

	onMount(() => loadMails());
</script>

<div class="bounces">
	<div class="note">
		<strong>Bounces</strong> refer to email notifications received regarging email delivery,
		usually for failed email deliveries. <strong>FBL</strong> (Feedback Loop) refers to email
		notifications sent by email providers when users mark an email as spam. Parsing these emails
		is complex since some email providers do not follow the standard DSN/ARF formats. Therefore,
		Hyvor Relay logs all bounces and their parse status for 30 days. If you see a bounce/FBL for
		a popular provider that is not parsed correctly, please report it on
		<a
			href="https://github.com/hyvor/relay/issues/new?template=bounce-fbl-parsing-issue.md"
			class="hds-link"
			target="_blank">Github</a
		> so we can improve the parsing logic.
	</div>

	{#if mails.length === 0 && !loading}
		<IconMessage empty message="No bounces found" />
	{:else}
		<div class="rows">
			{#each mails as mail}
				<BounceRow {mail} />
			{/each}
		</div>

		{#if hasMore}
			<div class="load-more">
				<LoadButton
					text="Load More"
					{loading}
					show={hasMore}
					on:click={() => loadMails(true)}
				/>
			</div>
		{/if}
	{/if}
</div>

<style>
	.bounces {
		padding: 30px 40px;
		overflow: auto;
	}
	.note {
		font-size: 14px;
		color: var(--text-light);
	}
	.rows {
		margin-top: 20px;
	}
	.load-more {
		margin-top: 20px;
		text-align: center;
	}
</style>
