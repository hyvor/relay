<script lang="ts">
	import type {Component, Snippet} from 'svelte';

	interface Props {
		title: string;
		children: Snippet;
		icon?: Component;
	}

	let { title, children, icon }: Props = $props();
</script>

<div class="feature">
	<div class="icon">
		{#if icon}
			<svelte:component this={icon} size={48} />
		{/if}
	</div>

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
		font-weight: 600;
	}

	.content {
		font-size: 16px;
		line-height: 24px;
		margin-top: 0;
		margin-bottom: 24px;
	}

	.icon {
		margin-bottom: 15px;
		width: 40px;
		height: 40px;
	}
</style>
