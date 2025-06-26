<script lang="ts">
	import type { SmtpConversation } from '../../../types';

	interface Props {
		// host => SmtpConversation
		conversations: Record<string, SmtpConversation>;
		sentMxHost: string | null;
	}

	let { conversations, sentMxHost }: Props = $props();

	let selectedConversation = $derived.by(() => {
		if (sentMxHost && conversations[sentMxHost]) {
			return conversations[sentMxHost];
		}

		const hosts = Object.keys(conversations);
		if (hosts.length > 0) {
			return conversations[hosts[0]];
		}

		return null;
	});
</script>

{#if selectedConversation}
	<div class="code">
		{#each selectedConversation.Steps as step}
			{#if step.Name !== 'dial'}
				<div class="step">
					<div class="client">
						{#if step.Name === 'data_close'}
							[DATA SENT]
						{:else}
							{step.Command}
						{/if}
					</div>
					<div class="server">{`${step.ReplyCode} ${step.ReplyText}`}</div>
				</div>
			{/if}
		{/each}
	</div>
{/if}

<style>
	.code {
		font-family: Consolas, Monaco, 'Lucida Console', 'Courier New', monospace;
		background-color: #1e1e2e;
		color: #cdd6f4;
		padding: 15px 20px;
		border-radius: 20px;
		overflow-x: auto;
		font-size: 14px;
		margin-top: 10px;
	}

	.step {
		margin-bottom: 10px;
	}

	.client,
	.server {
		white-space: pre;
		line-height: 1.4;
	}

	.client {
		color: #89b4fa;
	}
</style>
