<script lang="ts">
	import dayjs from 'dayjs';
	import type { SendAttempt } from '../../../../types';
	import { Tag } from '@hyvor/design/components';
	import SmtpConversation from './SmtpConversation.svelte';
	import MxHost from '../MxHost.svelte';

	interface Props {
		attempt: SendAttempt;
	}

	let { attempt }: Props = $props();

	function getDefaultHost() {
		if (attempt.responded_mx_host) {
			return attempt.responded_mx_host;
		}

		return attempt.resolved_mx_hosts[0] || null;
	}

	let currentHost = $state(getDefaultHost());
	let selectedConversation = $derived(
		currentHost ? attempt.smtp_conversations[currentHost] || null : null
	);
</script>

<div class="wrap">
	<div class="row">
		<div class="status">
			{#if attempt.status === 'accepted'}
				<Tag color="green">Accepted</Tag>
			{:else if attempt.status === 'deferred'}
				<Tag color="orange">Deferred</Tag>
			{:else if attempt.status === 'bounced'}
				<Tag color="red">Bounced</Tag>
			{:else if attempt.status === 'failed'}
				<Tag color="red">Failed</Tag>
			{/if}
		</div>
		<div class="message">
			{#if attempt.status === 'accepted'}
				Message accepted by {attempt.responded_mx_host}
			{:else if attempt.status === 'bounced'}
				<span class="error">
					{attempt.error || 'No error message'}
				</span>
			{:else}
				<span class="info">Pending</span>
			{/if}
		</div>
		<div class="time">
			{dayjs.unix(attempt.created_at).format('MMM D, YYYY h:mm A')}
		</div>
	</div>

	<div class="convos">
		<div class="hosts">
			{#each attempt.resolved_mx_hosts as host, i}
				<MxHost
					{host}
					selected={currentHost === host}
					index={i + 1}
					conversation={attempt.smtp_conversations[host] || null}
					onselect={(h) => (currentHost = h)}
				/>
			{/each}
		</div>

		{#if selectedConversation?.network_error}
			<div class="convo-error">Error: - {selectedConversation.network_error}</div>
		{/if}

		<div class="conversation">
			{#if selectedConversation && selectedConversation.steps.length}
				<SmtpConversation conversation={selectedConversation} />
			{:else}
				<div class="no-convo">No SMTP conversation available for this host.</div>
			{/if}
		</div>
	</div>
</div>

<style>
	.wrap {
		background-color: var(--hover);
		border: 1px solid var(--border);
		border-radius: 25px;
	}
	.row {
		display: grid;
		grid-template-columns: minmax(100px, 1fr) 2fr minmax(100px, 1fr);
		align-items: center;
		padding: 15px 25px;
		border-bottom: 1px solid var(--border);
	}
	.time {
		text-align: right;
	}
	.message {
		font-size: 14px;
		color: var(--text-light);
	}

	.convos {
		padding: 15px 25px;
	}

	.hosts {
		display: flex;
		flex-wrap: wrap;
		gap: 5px;
	}

	.no-convo {
		color: var(--text-light);
		padding: 10px;
		font-size: 12px;
	}

	.convo-error {
		color: var(--red);
		font-size: 14px;
		margin-top: 15px;
	}
</style>
