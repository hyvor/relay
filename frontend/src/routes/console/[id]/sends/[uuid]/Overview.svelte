<script lang="ts">
	import { DetailCards, DetailCard, Tag } from '@hyvor/design/components';
	import type { Send } from '../../../types';
	import SendStatus from '../RecipientStatuses.svelte';
	import RelativeTime from '../../../@components/content/RelativeTime.svelte';
	import AttemptRow from './AttemptRow.svelte';
	import RecipientStatus from '../RecipientStatus.svelte';

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

	// sort recipients by type and then by domain
	const recipients = $derived.by(() => {
		const r = [...send.recipients];
		return r.sort((a, b) => {
			const typeOrder = { to: 0, cc: 1, bcc: 2 };
			if (typeOrder[a.type] !== typeOrder[b.type]) {
				return typeOrder[a.type] - typeOrder[b.type];
			}
			const aDomain = a.address.split('@')[1] || '';
			const bDomain = b.address.split('@')[1] || '';

			return aDomain.localeCompare(bDomain);
		});
	});
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
							<div class="address">{recipient.address}</div>
							<RecipientStatus status={recipient.status} />
						</div>
					{/each}
				</div>
			</DetailCard>
		</div>
	</div>
</div>

<div class="attempts">
	<div class="attempts-title">Delivery Attempts</div>

	<div class="rows">
		{#each send.attempts as attempt}
			<AttemptRow {attempt} />
		{/each}
	</div>
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
		gap: 5px;
	}

	.address {
		flex: 1;
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
