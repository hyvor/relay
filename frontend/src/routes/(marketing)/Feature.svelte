<script lang="ts">
	import type { Snippet } from 'svelte';

	interface Props {
		title: string;
		children: Snippet;
	}

	let { title, children }: Props = $props();
</script>

<div>
	<div class="title">
		{title}
	</div>

	<div class="content">
		{@render children()}
	</div>
</div>

<style>
	.title {
		font-size: 20px;
		line-height: 28px;
		margin-top: 0;
		margin-bottom: 8px;
	}

	.content {
		font-size: 16px;
		line-height: 24px;
		margin-top: 0;
		margin-bottom: 24px;
	}
</style>
