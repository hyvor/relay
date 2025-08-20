<script lang="ts">
	import type { Send } from '../../types';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import { consoleUrlProject } from '../../lib/consoleUrl';
	import { Tag } from '@hyvor/design/components';
	import RecipientStatuses from './RecipientStatuses.svelte';

	interface Props {
		send: Send;
		refreshList: () => void;
	}

	let { send, refreshList }: Props = $props();
</script>

<a class="email" href={consoleUrlProject(`sends/${send.uuid}`)}>
	<div class="from">
		<div class="from-email">{send.from_address}</div>
		{#if send.from_name}
			<div class="from-name">{send.from_name}</div>
		{/if}
	</div>

	<div class="recipients">
		{#each send.recipients as recipient}
			<div class="recipient">
				<div class="recipient-email">
					{recipient.address}
					<Tag size="x-small">
						{recipient.type.toUpperCase()}
					</Tag>
				</div>
				{#if recipient.name}
					<div class="recipient-name">{recipient.name}</div>
				{/if}
			</div>
		{/each}
	</div>

	<div class="subject">{send.subject}</div>

	<div class="status-wrap">
		<RecipientStatuses recipients={send.recipients} />

		<div class="time">
			Sent <RelativeTime unix={send.created_at} />
		</div>
	</div>
</a>

<style>
	.email {
		display: grid;
		grid-template-columns: 2fr 2fr 2fr 1fr;
		padding: 15px 30px;
		text-align: left;
		width: 100%;
		gap: 15px;
		word-break: break-all;
	}
	.email:hover {
		background: var(--hover);
	}

	.from-name,
	.recipient-name {
		color: var(--text-light);
		font-size: 14px;
		margin-top: 1px;
	}

	.recipients {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.time {
		margin-top: 5px;
		font-size: 12px;
		color: var(--text-light);
	}
</style>
