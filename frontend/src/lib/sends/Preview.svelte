<script lang="ts">
	import type { SendContent } from '../../routes/console/types';

	interface Props {
		content: SendContent;
	}

	let { content }: Props = $props();
</script>

<div class="preview">
	{#if content.body_html}
		<div class="title">HTML</div>

		<div class="content">
			<iframe
				class="preview-iframe"
				srcdoc={content.body_html}
				sandbox=""
				frameborder="0"
				title="Email Preview"
			></iframe>
		</div>
	{/if}

	{#if content.body_text}
		<div class="title">Text</div>
		<div class="content text">{content.body_text}</div>
	{/if}
</div>

<style>
	.preview {
		padding: 10px 30px;
	}

	.title {
		font-size: 12px;
		font-weight: 600;
		margin-bottom: 10px;
		text-transform: uppercase;
		color: var(--text-light);
	}

	.content {
		border-radius: 20px;
		background: var(--hover);
		border-radius: var(--box-radius);
		border: 1px solid var(--border);
		margin-bottom: 25px;
		max-height: 500px;
		overflow: auto;
	}

	.text {
		white-space: pre-wrap;
		word-break: break-word;
		padding: 20px 25px;
	}

	iframe {
		width: 100%;
		height: 500px;
	}
</style>
