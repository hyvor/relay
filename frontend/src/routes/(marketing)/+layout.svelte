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
				href="/docs">/docs</Button
			>
			<Button
				size="small"
				color="input"
				variant={page.url.pathname.startsWith('/hosting') ? 'fill' : 'invisible'}
				as="a"
				href="/hosting">/hosting</Button
			>
			<Button
				size="small"
				as="a"
				href="https://github.com/hyvor/relay"
				target="_blank"
				rel="noopener noreferrer"
				color="input"
				variant="invisible"
			>
				{#snippet start()}
					<IconGithub size={14} />
				{/snippet}
				github
				{#snippet end()}
					<IconBoxArrowUpRight size={11} />
				{/snippet}
			</Button>
		</div>

		<Button size="small" as="a" href="/console">
			Go to Console
			{#snippet end()}
				&rarr;
			{/snippet}
		</Button>
	{/snippet}
</Header>

{@render children?.()}

<Footer />

<style>
	.docs {
		margin-right: 5px;
		display: flex;
		gap: 5px;
		align-items: center;
	}
</style>
