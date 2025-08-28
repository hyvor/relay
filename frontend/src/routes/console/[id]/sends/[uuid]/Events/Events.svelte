<script lang="ts">
	import type { Send } from '../../../../types';
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

		// add attempts
		for (const attempt of send.attempts) {
			events.push({
				timestamp: attempt.created_at,
				type: 'attempt',
				attempt
			});
		}

		// add feedback
		for (const feedback of send.feedback) {
			events.push({
				timestamp: feedback.created_at,
				type: 'feedback',
				feedback
			});
		}

		events.sort((a, b) => b.timestamp - a.timestamp);

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
