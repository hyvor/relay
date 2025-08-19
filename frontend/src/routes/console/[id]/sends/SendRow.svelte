<script lang="ts">
	import type { Send } from '../../types';
	import SendStatus from './SendStatus.svelte';
	import RelativeTime from '../../@components/content/RelativeTime.svelte';
	import { consoleUrlProject } from '../../lib/consoleUrl';
	import { Tag } from '@hyvor/design/components';

	interface Props {
		send: Send;
		refreshList: () => void;
	}

	let { send, refreshList }: Props = $props();

	/* const statusTimestamp = $derived(
		send.status === 'accepted'
			? send.accepted_at
			: send.status === 'bounced'
				? send.bounced_at
				: null
	); */
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
				<div class="recipient-data">
					<div class="recipient-email">
						{recipient.address}
					</div>
					{#if recipient.name}
						<div class="recipient-name">{recipient.name}</div>
					{/if}
				</div>
				<div class="recipient-type">
					<Tag size="x-small">
						{recipient.type.toUpperCase()}
					</Tag>
				</div>
			</div>
		{/each}
	</div>

	<div class="subject">{send.subject}</div>

	<div class="status-wrap">
		<Tag color="green" size="small">Accepted</Tag>

		<div class="time">
			Sent <RelativeTime unix={send.created_at} />
		</div>
	</div>
</a>

<style>
	.email {
		display: grid;
		grid-template-columns: 2fr 2fr 2fr 1fr;
		padding: 15px 25px;
		border-radius: var(--box-radius);
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

	.recipient {
		display: flex;
	}

	.recipient-data {
		flex: 1;
	}

	.subject {
	}

	.time {
		margin-top: 5px;
		font-size: 12px;
		color: var(--text-light);
	}
</style>
