<script lang="ts">
	import type { Component, Snippet } from 'svelte';

	interface Props {
		title: string;
		children: Snippet;
		icon?: Component;
		docsLink?: string;
		docsText?: string;
		docsBlank?: boolean;
	}

	let {
		title,
		children,
		icon,
		docsLink,
		docsText = 'Read the docs',
		docsBlank = false
	}: Props = $props();
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

	{#if docsLink}
		<div class="docs-link">
			<a href={docsLink} target={docsBlank ? '_blank' : undefined}>{docsText} &rarr;</a>
		</div>
	{/if}
</div>

<style>
	.feature {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		padding-bottom: 24px;
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
	}

	.icon {
		margin-bottom: 25px;
		width: 48px;
		height: 48px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.docs-link {
		margin-top: 15px;
		color: var(--text-light);
	}
	.docs-link a:hover {
		text-decoration: underline;
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
