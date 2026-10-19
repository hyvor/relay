<script>
	import { SpotlightSplit, GdprSeal, CcpaSeal, SsoSeal, IsoSeal } from '@hyvor/design/marketing';
</script>

<SpotlightSplit
	title="Enterprise-ready."
	description="Enterprise-grade security and compliance, without adding complexity to your email infrastructure."
	stats={['> 99.9% Uptime', 'Priority Support']}
	button={{ href: 'https://hyvor.com/enterprise', label: 'Contact Sales', external: true }}
	background="#243660"
	highlightColor="rgba(200, 220, 255, 0.9)"
>
	{#snippet content()}
		<div class="seals">
			<GdprSeal />
			<CcpaSeal />
			<SsoSeal />
			<IsoSeal />
		</div>
	{/snippet}
</SpotlightSplit>

<style>
	.seals {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 20px;
	}

	@media (max-width: 768px) {
		.seals {
			grid-template-columns: repeat(4, 1fr);
		}
	}

	@media (max-width: 480px) {
		.seals {
			grid-template-columns: repeat(2, 1fr);
		}
	}
</style>
