<script lang="ts">
	import type { Send } from '../../routes/console/types';
	import RelativeTime from '../../routes/console/@components/content/RelativeTime.svelte';
	import { Tag } from '@hyvor/design/components';
	import { getSortedRecipients } from './recipients';
	import RecipientStatus from './RecipientStatus.svelte';

	interface Props {
		send: Send;
		hrefBuilder: (send: Send) => string;
		showProject?: boolean;
	}

	let { send, hrefBuilder, showProject = false }: Props = $props();

	let recipients = $derived(getSortedRecipients(send.recipients));
	let showAllRecipients = $state(false);
	let recipientsToShow = $derived(showAllRecipients ? recipients : recipients.slice(0, 4));
	let hasMoreRecipients = $derived(recipients.length > 4);
</script>

<a class="email" class:with-project={showProject} href={hrefBuilder(send)}>
	{#if showProject && send.project}
		<div class="project">
			<div class="project-name">{send.project.name}</div>
			<div class="project-id">#{send.project.id}</div>
		</div>
	{/if}

	<div class="from">
		<div class="from-email">{send.from_address}</div>
		{#if send.from_name}
			<div class="from-name">{send.from_name}</div>
		{/if}

		<div class="time">
			Sent <RelativeTime unix={send.created_at} />
		</div>
	</div>

	<div class="recipients">
		{#each recipientsToShow as recipient}
			<div class="recipient">
				<div class="r-type">
					<Tag size="x-small">
						{recipient.type.toUpperCase()}
					</Tag>
				</div>
				<div class="r-email-name">
					<div class="r-email">
						{recipient.address}
					</div>
					{#if recipient.name}
						<div class="r-name">{recipient.name}</div>
					{/if}
				</div>
				<RecipientStatus {recipient} />
			</div>
		{/each}

		{#if hasMoreRecipients}
			<div class="show-more">
				<button
					onclick={(e) => {
						e.stopImmediatePropagation();
						e.preventDefault();
						showAllRecipients = !showAllRecipients;
					}}
				>
					{#if showAllRecipients}
						Show less
					{:else}
						Show more ({recipients.length - recipientsToShow.length})
					{/if}
				</button>
			</div>
		{/if}
	</div>

	<div class="subject">{send.subject}</div>
</a>

<style>
	.email {
		display: grid;
		grid-template-columns: 2fr 3fr 2fr;
		padding: 15px 30px;
		text-align: left;
		width: 100%;
		gap: 15px;
		word-break: break-all;
	}
	.email.with-project {
		grid-template-columns: 1.5fr 2fr 3fr 2fr;
	}
	.email:hover {
		background: var(--hover);
	}

	.project-name {
		font-weight: 600;
	}
	.project-id {
		color: var(--text-light);
		font-size: 12px;
		margin-top: 2px;
	}

	.from-name,
	.r-name {
		color: var(--text-light);
		font-size: 14px;
		margin-top: 1px;
	}

	.recipients {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.recipient {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.r-email-name {
		flex: 1;
	}

	.time {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 4px;
	}

	.show-more {
		font-size: 12px;
		color: var(--link);
	}
	.show-more button:hover {
		text-decoration: underline;
		cursor: pointer;
	}
</style>
