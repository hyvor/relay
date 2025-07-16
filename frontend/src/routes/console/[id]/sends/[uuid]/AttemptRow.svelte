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
			{#if attempt.status === 'accepted'}
				<Tag color="green">Accepted</Tag>
			{:else if attempt.status === 'deferred'}
				<Tag color="orange">Deferrer</Tag>
			{:else if attempt.status === 'bounced'}
				<Tag color="red">Bounced</Tag>
			{/if}
		</div>
		<div class="message">
			{#if attempt.status === 'accepted'}
				Message accepted by {attempt.accepted_mx_host}
			{:else if attempt.status === 'bounced'}
				<span class="error">{attempt.error || 'No error message'}</span>
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
			sentMxHost={attempt.accepted_mx_host}
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
