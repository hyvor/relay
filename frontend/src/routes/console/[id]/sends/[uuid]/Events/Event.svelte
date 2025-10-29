<script lang="ts">
	import dayjs from 'dayjs';
	import type { Event } from './events';
	import type {
		Send,
		SendAttempt,
		SendAttemptRecipient,
		SendFeedback,
		SmtpConversation
	} from '../../../../types';
	import IconHourglass from '@hyvor/icons/IconHourglass';
	import IconSend from '@hyvor/icons/IconSend';
	import IconChat from '@hyvor/icons/IconChat';
	import IconSlashCircle from '@hyvor/icons/IconSlashCircle';

	interface Props {
		event: Event;
		send: Send;
	}

	let { event, send }: Props = $props();

	let { message, description, color } = $derived.by(() => {
		switch (event.type) {
			case 'queued':
				return {
					message: `Queued for sending to ${event.recipients_count} recipient(s)`,
					description: null,
					color: 'var(--gray)'
				};
			case 'suppressed':
				return {
					message: `Ignored due to suppressions: <strong>${event.suppressed_recipients?.join(', ')}</strong>`,
					description:
						"Didn't attempt to send because these recipients are on the suppression list.",
					color: 'var(--red)'
				};
			case 'attempt':
				return getAttemptMessage(event.attempt!.attempt, event.attempt!.recipient);
			case 'feedback':
				return getFeedbackMessage(event.feedback!);
		}

		function getAttemptMessage(attempt: SendAttempt, attemptRecipient: SendAttemptRecipient) {
			const recipient = send.recipients.find((r) => r.id === attemptRecipient.recipient_id)!;

			if (attemptRecipient.recipient_status === 'accepted') {
				return {
					message: `Accepted: <strong>${recipient.address}</strong>`,
					description: 'Successfully delivered to the recipient mail server.',
					color: 'var(--green)'
				};
			} else if (attemptRecipient.recipient_status === 'deferred') {
				return {
					message: `Deferred, retrying later: <strong>${recipient.address}</strong>`,
					description: getAttemptDescription(),
					color: 'var(--orange)'
				};
			} else if (attemptRecipient.recipient_status === 'bounced') {
				const suppressedMessage = attemptRecipient.is_suppressed
					? ' (Added to suppression list and future sends will be ignored)'
					: '';

				return {
					message: `Bounced: <strong>${recipient.address}</strong>`,
					description: getAttemptDescription() + suppressedMessage,
					color: 'var(--red)'
				};
			} else {
				return {
					message: `Failed to deliver: <strong>${recipient.address}</strong>`,
					description: getAttemptDescription(),
					color: 'var(--red)'
				};
			}

			function getAttemptDescription(): string | null {
				if (!attempt.responded_mx_host) {
					return null;
				}

				const smtpConvo = attempt.smtp_conversations[attempt.responded_mx_host];

				if (!smtpConvo) {
					return null;
				}

				const rcptError = getRcptError(smtpConvo);

				if (rcptError) {
					return rcptError;
				}

				const lastStep = smtpConvo.steps[smtpConvo.steps.length - 1];

				return `${lastStep.reply_code} ${lastStep.reply_text}`;
			}

			function getRcptError(smtpConvo: SmtpConversation): string {
				let ret = '';

				for (const step of smtpConvo.steps) {
					if (step.name != 'rcpt') {
						continue;
					}
					if (step.command.includes(recipient.address) && step.reply_code >= 400) {
						if (ret) {
							ret += '\n';
						}
						ret += `${step.reply_code} ${step.reply_text}`;
					}
				}

				return ret;
			}
		}

		function getFeedbackMessage(feedback: SendFeedback) {
			const recipient = send.recipients.find((r) => r.id === feedback.recipient_id);
			const recipientEmail = recipient ? recipient.address : 'unknown recipient';

			if (feedback.type === 'bounce') {
				return {
					message: `Bounced: <strong>${recipientEmail}</strong>`,
					description:
						'Received a bounce notification from the recipient mail server. (Added to suppression list and future sends will be ignored)',
					color: 'var(--red)'
				};
			} else {
				return {
					message: `Marked as spam: <strong>${recipientEmail}</strong>`,
					description: null,
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
		{:else if event.type === 'suppressed'}
			<IconSlashCircle />
		{:else if event.type === 'attempt'}
			<IconSend />
		{:else if event.type === 'feedback'}
			<IconChat />
		{/if}
	</div>

	<div class="message-wrap">
		<div class="message">{@html message}</div>
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
