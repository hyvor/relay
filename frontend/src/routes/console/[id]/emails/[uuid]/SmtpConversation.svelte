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

	function codeClass(code: number): string {
		if (code >= 400) {
			return 'error';
		}
		return '';
	}
</script>

{#if selectedConversation}
	<div class="code">
		{#each selectedConversation.Steps as step}
			{#if step.Name !== 'dial'}
				<div class="step">
					<div class="client">
						{#if step.Name === 'data_close'}
							{`<DATACLOSE>`}
						{:else}
							{step.Command}
						{/if}
					</div>
					<div class="server {codeClass(step.ReplyCode)}">
						{`${step.ReplyCode} ${step.ReplyText}`}
					</div>
				</div>
			{/if}
		{/each}
	</div>
{/if}

<style>
	.code {
		font-family:
			Consolas,
			Monaco,
			Andale Mono,
			Ubuntu Mono,
			monospace;
		background-color: #282c34;
		color: #abb2bf;
		padding: 15px 20px;
		border-radius: 20px;
		overflow-x: auto;
		font-size: 14px;
		margin-top: 15px;
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
		color: #61aeee;
	}

	.server.error {
		color: #e06c75;
	}
</style>
