<script lang="ts">
	import { DetailCard, Tag, toast } from '@hyvor/design/components';
	import type { Send } from '../../../types';
	import RelativeTime from '../../../@components/content/RelativeTime.svelte';
	import RecipientStatus from '../RecipientStatus.svelte';
	import { getSortedRecipients } from './recipients';
	import byteFormatter from '$lib/byteFormatter';
	import Events from './Events/Events.svelte';
	import Attempts from './Attempts/Attempts.svelte';
	import QueuedCallout from './QueuedCallout.svelte';
	import FailedCallout from './FailedCallout.svelte';
	import RetryModal from './RetryModal.svelte';
	import { retrySend } from '../../../lib/actions/emailActions';

	let { send, onSendUpdate }: { send: Send; onSendUpdate: (send: Send) => void } = $props();

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
	const failedRecipients = $derived(recipients.filter((r) => r.status === 'failed'));
	const hasFailedRecipients = $derived(failedRecipients.length > 0);

	let retryLoading = $state(false);
	let showRetryModal = $state(false);
	let tryNowLoading = $state(false);

	async function handleRetryConfirm(recipientIds: number[], mode: 'now' | 'schedule', scheduledDate?: string) {
		let sendAfter: number | undefined;
		if (mode === 'schedule') {
			if (!scheduledDate) {
				toast.error('Please select a date and time');
				return;
			}
			sendAfter = Math.floor(new Date(scheduledDate).getTime() / 1000);
		}

		retryLoading = true;
		try {
			const result = await retrySend(send.id, sendAfter, recipientIds);
			const msg = mode === 'now'
				? `${result.retried_recipients} recipient(s) re-queued for retry`
				: `${result.retried_recipients} recipient(s) scheduled for retry`;
			toast.success(msg);
			showRetryModal = false;
			onSendUpdate(result.send);
		} catch (err: any) {
			toast.error(err.message || 'Failed to retry send');
		} finally {
			retryLoading = false;
		}
	}

	async function handleTryNow() {
		tryNowLoading = true;
		try {
			const result = await retrySend(send.id);
			toast.success('Send triggered for immediate delivery');
			onSendUpdate(result.send);
		} catch (err: any) {
			toast.error(err.message || 'Failed to trigger send');
		} finally {
			tryNowLoading = false;
		}
	}
</script>

<div class="basics">
	{#if send.queued}
		<QueuedCallout after={send.send_after} {recipients} onTryNow={handleTryNow} {tryNowLoading} />
	{/if}

	{#if hasFailedRecipients && !send.queued}
		<FailedCallout onRetryClick={() => (showRetryModal = true)} />
	{/if}

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
							<RecipientStatus {recipient} />
						</div>
					{/each}
				</div>
			</DetailCard>
		</div>

		<DetailCard label="Size" content={byteFormatter(send.size_bytes)} />
	</div>
</div>

<div class="events">
	<Events {send} />
</div>

<div class="attempts">
	<Attempts {send} />
</div>

<RetryModal
	bind:show={showRetryModal}
	{failedRecipients}
	loading={retryLoading}
	onConfirm={handleRetryConfirm}
/>

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
</style>
