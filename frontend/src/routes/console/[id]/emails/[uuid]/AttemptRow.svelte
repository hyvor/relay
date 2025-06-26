<script lang="ts">
	import dayjs from 'dayjs';
	import type { SendAttempt } from '../../../types';
	import { Tag } from '@hyvor/design/components';
	import SmtpConversation from './SmtpConversation.svelte';

	interface Props {
		attempt: SendAttempt;
	}

	let { attempt }: Props = $props();
</script>

<div class="wrap">
	<div class="row">
		<div class="status">
			{#if attempt.status === 'sent'}
				<Tag color="green">Sent</Tag>
			{:else if attempt.status === 'failed'}
				<Tag color="red">Hard Fail</Tag>
			{:else if attempt.status === 'queued'}
				<Tag color="orange">Soft Fail (Re-queued)</Tag>
			{/if}
		</div>
		<div class="message">
			{#if attempt.status === 'sent'}
				Message accepted by {attempt.sent_mx_host}
			{:else if attempt.status === 'failed'}
				<span class="error">{attempt.er || 'No error message'}</span>
			{:else}
				<span class="info">Pending</span>
			{/if}
		</div>
		<div class="time">
			{dayjs.unix(attempt.created_at).format('MMM D, YYYY h:mm A')}
		</div>
	</div>
	<div class="conversation">
		<SmtpConversation
			conversations={attempt.smtp_conversations}
			sentMxHost={attempt.sent_mx_host}
		/>
	</div>
</div>

<style>
	.wrap {
		padding: 15px 25px;
		background-color: var(--hover);
		border: 1px solid var(--border);
		border-radius: 25px;
	}
	.row {
		display: grid;
		grid-template-columns: minmax(100px, 1fr) 2fr minmax(100px, 1fr);
		align-items: center;
	}
	.time {
		text-align: right;
	}
	.message {
		font-size: 14px;
		color: var(--text-light);
	}
</style>
