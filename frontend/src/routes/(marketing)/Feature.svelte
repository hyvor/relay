<script lang="ts">
	import type { Component, Snippet } from 'svelte';

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
	.feature {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
	}

	.title {
		font-size: 22px;
		line-height: 28px;
		margin-top: 0;
		margin-bottom: 12px;
		font-weight: 600;
	}

	.content {
		font-size: 18px;
		line-height: 28px;
		margin-top: 0;
		margin-bottom: 24px;
	}

	.icon {
		margin-bottom: 25px;
		width: 48px;
		height: 48px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	/* Mobile styles (max 976px) */
	@media (max-width: 976px) {
		.feature {
			width: 100%;
			text-align: center;
			align-items: center;
		}

		.icon {
			margin-bottom: 15px;
			display: flex;
			justify-content: center;
			align-items: center;
			margin-left: auto;
			margin-right: auto;
		}
	}
</style>
