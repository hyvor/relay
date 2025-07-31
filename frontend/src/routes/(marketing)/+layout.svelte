<script lang="ts">
	import { page } from '$app/state';
	import { Button } from '@hyvor/design/components';
	import { Footer, Header } from '@hyvor/design/marketing';
	import IconBoxArrowUpRight from '@hyvor/icons/IconBoxArrowUpRight';
	import IconGithub from '@hyvor/icons/IconGithub';

	interface Props {
		children?: import('svelte').Snippet;
	}

	let { children }: Props = $props();
</script>

<Header logo="https://hyvor.com/api/public/logo/core.svg" subName="Relay" darkToggle={false}>
	{#snippet end()}
		<div class="docs">
			<Button
				size="small"
				color="input"
				variant={page.url.pathname.startsWith('/docs') ? 'fill' : 'invisible'}
				as="a"
				href="/docs">Docs</Button
			>
			<Button
				size="small"
				color="input"
				variant={page.url.pathname.startsWith('/hosting') ? 'fill' : 'invisible'}
				as="a"
				href="/hosting">Hosting</Button
			>
		</div>
		<Button
			size="small"
			as="a"
			href="https://github.com/hyvor/relay"
			target="_blank"
			rel="noopener noreferrer"
		>
			{#snippet start()}
				<IconGithub size={14} />
			{/snippet}
			Github
			{#snippet end()}
				<IconBoxArrowUpRight size={11} />
			{/snippet}
		</Button>
	{/snippet}
</Header>

{@render children?.()}

<Footer />

<style>
	.docs {
		margin-right: 5px;
	}
</style>
