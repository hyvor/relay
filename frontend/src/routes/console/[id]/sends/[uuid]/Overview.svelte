<script lang="ts">
	import { DetailCards, DetailCard, Tag, IconMessage } from '@hyvor/design/components';
	import type { Send } from '../../../types';
	import SendStatus from '../RecipientStatuses.svelte';
	import RelativeTime from '../../../@components/content/RelativeTime.svelte';
	import AttemptRow from './AttemptRow.svelte';
	import RecipientStatus from '../RecipientStatus.svelte';
	import { getSortedRecipients } from './recipients';
	import byteFormatter from '$lib/byteFormatter';

	let { send }: { send: Send } = $props();

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

	const recipients = $derived(getSortedRecipients(send.recipients));
</script>

<div class="basics">
	<div class="grid">
		<DetailCard label="From" content={send.from_address} />

		<DetailCard label="Subject" content={send.subject || 'No subject'} />

		<DetailCard label="Date">
			<div>
				{formatTimestamp(send.created_at)}
				<span class="relative-time">(<RelativeTime unix={send.created_at} />)</span>
			</div>
		</DetailCard>

		<div class="recipients-wrap">
			<DetailCard label="Recipients">
				<div class="recipients">
					{#each recipients as recipient}
						<div class="recipient">
							<div class="type">
								<Tag size="x-small">
									{recipient.type.toUpperCase()}
								</Tag>
							</div>
							<div class="address-name">
								<div class="address">{recipient.address}</div>
								{#if recipient.name}
									<div class="name">{recipient.name}</div>
								{/if}
							</div>
							<RecipientStatus status={recipient.status} />
						</div>
					{/each}
				</div>
			</DetailCard>
		</div>

		<DetailCard label="Size" content={byteFormatter(send.size_bytes)} />
	</div>
</div>

<div class="attempts">
	<div class="attempts-title">Delivery Attempts</div>

	{#if send.attempts.length}
		<div class="rows">
			{#each send.attempts as attempt}
				<AttemptRow {attempt} />
			{/each}
		</div>
	{:else}
		<IconMessage empty message="No delivery attempts" padding={60} />
	{/if}
</div>

<style>
	.grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
		gap: 15px;
	}

	.recipients-wrap {
		grid-column: span 2;
	}

	.recipients {
		display: flex;
		flex-direction: column;
		gap: 5px;
		word-break: break-all;
	}

	.recipient {
		display: flex;
		align-items: center;
		gap: 10px;
	}

	.address-name {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 2px;
	}

	.name {
		font-size: 12px;
		color: var(--text-light);
	}

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
