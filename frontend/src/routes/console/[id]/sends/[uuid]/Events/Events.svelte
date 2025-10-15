<script lang="ts">
	import type {
		Send,
		SendAttempt,
		SendAttemptStatus,
		SendRecipientStatus
	} from '../../../../types';
	import { default as EventComponent } from './Event.svelte';
	import type { Event } from './events';

	interface Props {
		send: Send;
	}

	let { send }: Props = $props();

	function getEvents(send: Send): Event[] {
		const events: Event[] = [];

		events.push({
			timestamp: send.created_at,
			type: 'queued',
			recipients_count: send.recipients.length
		});

		// add suppression failures
		const suppressed = send.recipients.filter((r) => r.status === 'suppressed');
		if (suppressed.length > 0) {
			events.push({
				timestamp: send.created_at,
				type: 'suppressed',
				suppressed_recipients: suppressed.map((r) => r.address)
			});
		}

		// add attempts
		for (const attempt of send.attempts) {
			let generatedAttempts: SendAttempt[] = [];

			if (attempt.status === 'partial') {
				// each recipient will have their own event, grouped by recipient statuses
				const recipientIdsByStatus: Partial<Record<SendRecipientStatus, number[]>> = {};

				for (const recipient of send.recipients) {
					const status = attempt.recipient_statuses[recipient.id];
					if (!recipientIdsByStatus[status]) {
						recipientIdsByStatus[status] = [];
					}
					recipientIdsByStatus[status].push(recipient.id);
				}

				for (const [status, recipientIds] of Object.entries(recipientIdsByStatus)) {
					if (recipientIds.length === 0) continue;

					generatedAttempts.push({
						...attempt,
						status: status as SendAttemptStatus,
						recipient_ids: recipientIds
					});
				}
			} else {
				generatedAttempts = [attempt];
			}

			for (const ga of generatedAttempts) {
				events.push({
					timestamp: ga.created_at,
					type: 'attempt',
					attempt: ga
				});
			}
		}

		// add feedback
		for (const feedback of send.feedback) {
			events.push({
				timestamp: feedback.created_at,
				type: 'feedback',
				feedback
			});
		}

		// last queued, then suppressed, then by timestamp
		events.sort((a, b) => {
			if (a.type === 'queued') return 1;
			if (b.type === 'queued') return -1;

			if (a.type === 'suppressed') return 1;
			if (b.type === 'suppressed') return -1;

			return b.timestamp - a.timestamp;
		});

		return events;
	}

	const events = $derived(getEvents(send));
</script>

<div class="events">
	<div class="title">Events</div>

	{#if events.length}
		<div class="rows">
			{#each events as event}
				<EventComponent {event} {send} />
			{/each}
		</div>
	{/if}
</div>

<style>
	.events {
		border-top: 1px solid var(--border);
		padding: 20px 30px;
	}
	.title {
		font-size: 18px;
		font-weight: bold;
		margin-bottom: 20px;
	}
	.rows {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
</style>
