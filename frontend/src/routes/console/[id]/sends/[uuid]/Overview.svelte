<script lang="ts">
	import { Button, Callout, DetailCard, Modal, Tag, TextInput, toast } from '@hyvor/design/components';
	import type { Send } from '../../../types';
	import RelativeTime from '../../../@components/content/RelativeTime.svelte';
	import RecipientStatus from '../RecipientStatus.svelte';
	import { getSortedRecipients } from './recipients';
	import byteFormatter from '$lib/byteFormatter';
	import Events from './Events/Events.svelte';
	import Attempts from './Attempts/Attempts.svelte';
	import QueuedCallout from './QueuedCallout.svelte';
	import IconArrowClockwise from '@hyvor/icons/IconArrowClockwise';
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
	const hasFailedRecipients = $derived(recipients.some((r) => r.status === 'failed'));

	let retryLoading = $state(false);
	let showScheduleModal = $state(false);
	let scheduledDate = $state('');

	async function handleRetryNow() {
		retryLoading = true;
		try {
			const result = await retrySend(send.id);
			toast.success(`${result.retried_recipients} recipient(s) re-queued for retry`);
			onSendUpdate(result.send);
		} catch (err: any) {
			toast.error(err.message || 'Failed to retry send');
		} finally {
			retryLoading = false;
		}
	}

	async function handleScheduleRetry() {
		if (!scheduledDate) {
			toast.error('Please select a date and time');
			return;
		}

		const timestamp = Math.floor(new Date(scheduledDate).getTime() / 1000);

		retryLoading = true;
		try {
			const result = await retrySend(send.id, timestamp);
			toast.success(`${result.retried_recipients} recipient(s) scheduled for retry`);
			showScheduleModal = false;
			scheduledDate = '';
			onSendUpdate(result.send);
		} catch (err: any) {
			toast.error(err.message || 'Failed to schedule retry');
		} finally {
			retryLoading = false;
		}
	}
</script>

<div class="basics">
	{#if send.queued}
		<QueuedCallout after={send.send_after} {recipients} />
	{/if}

	{#if hasFailedRecipients && !send.queued}
		<div class="retry-callout">
			<Callout type="warning">
				{#snippet icon()}
					<IconArrowClockwise />
				{/snippet}
				Some recipients failed to receive this email. You can retry sending to the failed recipients.
				<div class="retry-actions">
					<Button
						size="small"
						color="orange"
						on:click={handleRetryNow}
						loading={retryLoading}
					>
						Retry Now
					</Button>
					<Button
						size="small",
						variant="outline"
						color="orange"
						on:click={() => (showScheduleModal = true)}
					>
						Schedule Retry
					</Button>
				</div>
			</Callout>
		</div>
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

<Modal
	bind:show={showScheduleModal}
	title="Schedule Retry"
	footer={{ confirm: { text: 'Schedule' } }}
	on:confirm={handleScheduleRetry}
	loading={retryLoading}
>
	<p>Choose when to retry sending to failed recipients.</p>
	<TextInput type="datetime-local" bind:value={scheduledDate} block />
</Modal>

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
	.retry-callout {
		margin-bottom: 20px;
	}
	.retry-actions {
		display: flex;
		gap: 8px;
		margin-top: 10px;
	}
</style>
