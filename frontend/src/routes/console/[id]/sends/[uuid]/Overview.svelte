<script lang="ts">
	import { DetailCards, DetailCard } from '@hyvor/design/components';
	import type { Email } from '../../../types';
	import SendStatus from '../SendStatus.svelte';
	import RelativeTime from '../../../@components/content/RelativeTime.svelte';
	import AttemptRow from './AttemptRow.svelte';

	let { send }: { send: Email } = $props();

	function formatTimestamp(timestamp: number | undefined): string {
		if (!timestamp) return 'N/A';
		const date = new Date(timestamp * 1000);
		return date.toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
			hour12: true
		});
	}
</script>

<div class="basics">
	<DetailCards>
		<DetailCard label="From" content={send.from_address} />

		<DetailCard label="To" content={send.to_address} />

		<DetailCard label="Subject" content={send.subject || 'No subject'} />

		<DetailCard label="Status">
			<SendStatus status={send.status} />
		</DetailCard>

		<DetailCard label="Created">
			<div>{formatTimestamp(send.created_at)}</div>
			<div class="relative-time">(<RelativeTime unix={send.created_at} />)</div>
		</DetailCard>

		{#if send.accepted_at}
			<DetailCard label="Sent">
				<div>{formatTimestamp(send.accepted_at)}</div>
				<div class="relative-time">(<RelativeTime unix={send.accepted_at} />)</div>
			</DetailCard>
		{/if}

		{#if send.bounced_at}
			<DetailCard label="Failed">
				<div>{formatTimestamp(send.bounced_at)}</div>
				<div class="relative-time">(<RelativeTime unix={send.bounced_at} />)</div>
			</DetailCard>
		{/if}

		<DetailCard label="UUID">
			<div class="uuid">{send.uuid}</div>
		</DetailCard>
	</DetailCards>
</div>

<div class="attempts">
	<div class="attempts-title">Attempts</div>

	<div class="rows">
		{#each send.attempts as attempt}
			<AttemptRow {attempt} />
		{/each}
	</div>
</div>

<style>
	.basics {
		margin-bottom: 15px;
		padding: 10px 25px 20px;
	}
	.relative-time {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 4px;
	}
	.attempts {
		border-top: 1px solid var(--border);
		padding: 20px 25px;
	}
	.attempts-title {
		font-size: 18px;
		font-weight: bold;
		margin-bottom: 20px;
		padding-left: 10px;
	}
	.rows {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
</style>
