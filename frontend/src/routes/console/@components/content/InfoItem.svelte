<script lang="ts">
	interface Props {
		label: string;
		value?: string | number;
		children?: import('svelte').Snippet;
	}

	let { label, value, children }: Props = $props();
</script>

<div class="info-item">
	<div class="label">{label}</div>
	<div class="value">
		{#if children}
			{@render children()}
		{:else}
			{value || 'N/A'}
		{/if}
	</div>
</div>

<style>
	.info-item {
		background: var(--accent-lightest);
		padding: 15px;
		border-radius: var(--box-radius);
		border: 1px solid var(--border);
	}

	.info-item .label {
		display: block;
		font-size: 12px;
		font-weight: 600;
		color: var(--text-light);
		text-transform: uppercase;
		letter-spacing: 0.5px;
		margin-bottom: 8px;
	}

	.info-item .value {
		font-size: 14px;
		word-break: break-word;
	}
</style> 