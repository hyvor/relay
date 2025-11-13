<script lang="ts">
	import { page } from '$app/state';
	import { Button } from '@hyvor/design/components';
	import { Footer, FooterLinkList, Header } from '@hyvor/design/marketing';
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
				Github
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

<Footer>
	{#snippet center()}
		<div style="display:flex">
			<FooterLinkList title="Product">
				<a href="/#pricing">Pricing</a>
				<a href="/docs">Docs</a>
				<a href="/hosting">Hosting</a>
			</FooterLinkList>

			<FooterLinkList title="Legal">
				<a href="https://hyvor.com/docs/hosting-license" target="_blank">
					Self-Hosting License FAQ
				</a>
			</FooterLinkList>

			<FooterLinkList title="HYVOR">
				<a href="https://hyvor.com" target="_blank"> About Us </a>
				<a href="https://hyvor.com/support" target="_blank"> Support </a>
				<a href="https://hyvor.com/enterprise" target="_blank"> Enterprise </a>
				<a href="https://hyvor.com/updates" target="_blank"> Updates </a>
				<a href="https://status.hyvor.com" target="_blank"> System Status </a>
			</FooterLinkList>
		</div>
	{/snippet}
</Footer>

<style>
	.docs {
		margin-right: 5px;
		display: flex;
		gap: 5px;
		align-items: center;
	}
</style>
