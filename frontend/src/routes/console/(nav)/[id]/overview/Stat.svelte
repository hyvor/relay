<script lang="ts">
	import type { Snippet } from 'svelte';

	interface Props {
		title: string | Snippet;
		children?: Snippet;
	}

	let { title, children }: Props = $props();
</script>

<div class="stat">
	<div class="title-wrap">
		<div class="title">
			{#if typeof title === 'string'}
				{title}
			{:else}
				{@render title?.()}
			{/if}
		</div>
	</div>
	<div class="value-wrap">
		<span class="value">
			{@render children?.()}
		</span>
	</div>
</div>

<style>
	.stat {
		flex: 1;
	}
	.title-wrap {
		margin-bottom: 5px;
	}
	.title {
		font-size: 16px;
		font-weight: bold;
	}
	/* .subtitle {
		font-size: 14px;
		color: var(--text-light);
		margin-top: 2px;
	} */
	.value {
		font-size: 35px;
	}
	/* .below {
		margin-top: 5px;
		font-size: 14px;
		color: var(--text-light);
	} */
</style>
