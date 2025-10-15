<script lang="ts">
	import type { SmtpConversation } from '../../../../types';

	interface Props {
		conversation: SmtpConversation;
	}

	let { conversation }: Props = $props();

	function codeClass(code: number): string {
		if (code >= 400 && code < 500) {
			return 'warning';
		} else if (code >= 500) {
			return 'error';
		}
		return '';
	}
</script>

<div class="code">
	{#each conversation.steps as step}
		{#if step.name !== 'dial'}
			<div class="step">
				<div class="client">
					{#if step.name === 'data_close'}
						{`<DATACLOSE>`}
					{:else}
						{step.command}
					{/if}
				</div>
				<div class="server {codeClass(step.reply_code)}">
					{`${step.reply_code} ${step.reply_text}`}
				</div>
			</div>
		{/if}
	{/each}
</div>

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

	.server.warning {
		color: #e6c07b;
	}
</style>
