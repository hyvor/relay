<script lang="ts">
	import { page } from '$app/state';
	import IconBoxArrowUpRight from '@hyvor/icons/IconBoxArrowUpRight';
	import IconGithub from '@hyvor/icons/IconGithub';
	import { Header } from '@hyvor/design/marketing';
	import { Button } from '@hyvor/design/components';
</script>

<Header product="relay" subName="Relay" darkToggle={false}>
	{#snippet end()}
		<div class="docs">
			<Button
				size="small"
				color="accent"
				variant={page.url.pathname.startsWith('/docs') ? 'fill-light' : 'invisible'}
				as="a"
				href="/docs">Docs</Button
			>
			<Button
				size="small"
				color="accent"
				variant={page.url.pathname.startsWith('/hosting') ? 'fill-light' : 'invisible'}
				as="a"
				href="/hosting">Hosting</Button
			>
			<Button
				size="small"
				as="a"
				href="https://github.com/hyvor/relay"
				target="_blank"
				rel="noopener noreferrer"
				color="accent"
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

<style>
	.docs {
		display: flex;
		gap: 8px;
		align-items: center;
		margin-right: 12px;
	}
</style>
