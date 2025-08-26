<script lang="ts">
	import dayjs from 'dayjs';
	import type { Event } from './events';
	import type { SendAttempt } from '../../../../types';
	import IconHourglass from '@hyvor/icons/IconHourglass';
	import IconSend from '@hyvor/icons/IconSend';
	import IconChat from '@hyvor/icons/IconChat';

	interface Props {
		event: Event;
	}

	let { event }: Props = $props();

	let { message, description, color } = $derived.by(() => {
		switch (event.type) {
			case 'queued':
				return {
					message: `Queued for sending to ${event.recipients_count} recipient(s)`,
					description: null,
					color: 'var(--gray)'
				};
			case 'attempt':
				return getAttemptMessage(event.attempt!);
			case 'feedback':
				return {
					message: 'Feedback received',
					description: null,
					color: 'var(--red)'
				};
		}

		function getAttemptMessage(attempt: SendAttempt) {
			if (attempt.status === 'accepted') {
				return {
					message: `Accepted by ${attempt.domain}`,
					description: null,
					color: 'var(--green)'
				};
			} else if (attempt.status === 'deferred') {
				return {
					message: `Deferred by ${attempt.domain}, retrying later.`,
					description: attempt.error,
					color: 'var(--orange)'
				};
			} else {
				return {
					message: `Bounced by ${attempt.domain}`,
					description: attempt.error,
					color: 'var(--red)'
				};
			}
		}
	});
</script>

<div class="event" style="--color: {color}">
	<div class="icon">
		{#if event.type === 'queued'}
			<IconHourglass />
		{:else if event.type === 'attempt'}
			<IconSend />
		{:else if event.type === 'feedback'}
			<IconChat />
		{/if}
	</div>

	<div class="message-wrap">
		<div class="message">{message}</div>
		<div class="description">
			{description}
		</div>
		<div class="timestamp">
			{dayjs.unix(event.timestamp).toDate().toLocaleString()}
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

	.icon {
		width: 25px;
		height: 25px;
		display: flex;
		align-items: center;
		justify-content: flex-start;
		color: var(--color);
	}

	.timestamp {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 3px;
	}

	.message-wrap {
		flex: 1;
	}

	.description {
		font-size: 12px;
		color: var(--text-light);
		margin-top: 1px;
	}

	.dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background-color: var(--color);
	}
</style>
