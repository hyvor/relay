<script>
	import { Button } from '@hyvor/design/components';
	import { page } from '$app/state';
	import { Footer, FooterLinkList, Header } from '@hyvor/design/marketing';
	import IconBoxArrowUpRight from '@hyvor/icons/IconBoxArrowUpRight';
	import IconGithub from '@hyvor/icons/IconGithub';
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
<div class="error-page">
	<h1 class="heading">404 - Page Not Found</h1>
	<p class="description">
		Sorry, the page you are looking for might have been removed, had its name changed, or is
		temporarily unavailable.
	</p>
	<Button as="a" href="/">Go to Homepage</Button>
</div>

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
	.error-page {
		padding: 20px;
		text-align: center;
		height: calc(100vh - 100px);
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
	}
	.heading {
		font-size: 38px;
		margin: 0;
	}
	.description {
		/*reduce the width of the text to fit the screen*/
		max-width: 100%;
		width: 450px;
	}
</style>
