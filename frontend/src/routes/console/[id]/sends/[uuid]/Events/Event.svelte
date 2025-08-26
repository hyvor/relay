<script lang="ts">
	import dayjs from 'dayjs';
	import type { Event } from './events';

	interface Props {
		event: Event;
	}

	let { event }: Props = $props();

	let message = $derived.by(() => {
		switch (event.type) {
			case 'queued':
				return `Email queued; sending to ${event.recipients_count} recipient(s)`;
			case 'accepted':
				return `Email accepted by ${event.recipient_name || event.recipient_address}`;
			case 'deferred':
				return `Email deferred by ${event.recipient_name || event.recipient_address}`;
			case 'bounced':
				return `Email bounced by ${event.recipient_name || event.recipient_address}`;
			case 'complaint':
				return `Complaint received from ${event.recipient_name || event.recipient_address}`;
			default:
				return 'Unknown event';
		}
	});

	let color = $derived.by(() => {
		return {
			queued: 'var(--gray)',
			accepted: 'var(--green)',
			deferred: 'var(--blue)',
			bounced: 'var(--orange)',
			complaint: 'var(--red)'
		}[event.type];
	});

	let description = null;
</script>

<div class="event" style="--color: {color}">
	<div class="timestamp">
		{dayjs.unix(event.timestamp).toDate().toLocaleString()}
	</div>
	<div class="message-wrap">
		<div class="message">{message}</div>
		<div class="description">
			{description}
		</div>
	</div>
	<div class="dot-wrap">
		<div class="dot"></div>
	</div>
</div>

<style>
	.event {
		padding: 8px 25px;
		border-radius: 20px;
		border: 1px solid color-mix(in srgb, var(--color) 20%, transparent);
		background-color: color-mix(in srgb, var(--color) 10%, transparent);
		display: flex;
		align-items: center;
		gap: 10px;
		font-size: 14px;
	}

	.timestamp {
		font-size: 12px;
		color: var(--text-light);
		width: 130px;
	}

	.message-wrap {
		flex: 1;
	}

	.dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background-color: var(--color);
	}
</style>
