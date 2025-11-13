<script lang="ts">
	import { Footer, FooterLinkList } from '@hyvor/design/marketing';
</script>

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
