<script lang="ts">
	import dayjs from 'dayjs';
	import { Tag } from '@hyvor/design/components';
	import type { SudoSend, SudoSendRecipientStatus } from '../sudoTypes';

	interface Props {
		send: SudoSend;
	}

	let { send }: Props = $props();

	let showAllRecipients = $state(false);
	let recipientsToShow = $derived(
		showAllRecipients ? send.recipients : send.recipients.slice(0, 4)
	);
	let hasMoreRecipients = $derived(send.recipients.length > 4);

	function statusMeta(status: SudoSendRecipientStatus): { color: string; text: string } {
		switch (status) {
			case 'accepted':
				return { color: 'green', text: 'Accepted' };
			case 'bounced':
				return { color: 'red', text: 'Bounced' };
			case 'complained':
				return { color: 'red', text: 'Complained' };
			case 'queued':
				return { color: 'default', text: 'Queued' };
			case 'deferred':
				return { color: 'orange', text: 'Deferred' };
			case 'suppressed':
				return { color: 'red', text: 'Suppressed' };
			case 'failed':
				return { color: 'red', text: 'Failed' };
		}
	}
</script>

<a class="row" href={`/console/${send.project.id}/sends/${send.uuid}`}>
	<div class="project">
		<div class="project-name">{send.project.name}</div>
		<div class="project-id">#{send.project.id}</div>
	</div>

	<div class="from">
		<div class="from-email">{send.from_address}</div>
		{#if send.from_name}
			<div class="from-name">{send.from_name}</div>
		{/if}
		<div class="time">
			{dayjs.unix(send.created_at).fromNow()}
		</div>
	</div>

	<div class="recipients">
		{#each recipientsToShow as recipient (recipient.id)}
			{@const meta = statusMeta(recipient.status)}
			<div class="recipient">
				<div class="r-email">{recipient.address}</div>
				<Tag size="small" color={meta.color as any}>
					{meta.text}
				</Tag>
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
						Show more ({send.recipients.length - recipientsToShow.length})
					{/if}
				</button>
			</div>
		{/if}
	</div>

	<div class="subject">{send.subject ?? ''}</div>
</a>

<style>
	.row {
		display: grid;
		grid-template-columns: 1.5fr 2fr 3fr 2fr;
		padding: 15px 30px;
		text-align: left;
		width: 100%;
		gap: 15px;
		word-break: break-all;
	}
	.row:hover {
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

	.from-name {
		color: var(--text-light);
		font-size: 14px;
		margin-top: 1px;
	}

	.recipients {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.recipient {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.r-email {
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
