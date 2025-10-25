<script>
	import Plan from './Plan.svelte';
</script>

<div class="wrap">
	<div class="hds-container">
		<h3>Pricing</h3>
		<div class="pricing">
			<div class="self-hosted section hds-box">
				<div class="main-title">Self-Hosted</div>
				<div class="plans">
					<Plan
						name="Open-Source"
						price="Free"
						priceSubtext="Unlimited usage"
						divider
						features={[
							'AGPL-3.0 Obligations',
							'Community Support',
							'All Features Included',
							'Free Forever'
						]}
						ctaText="Start Self-Hosting"
						ctaLink="/hosting"
					/>
					<Plan
						name="Commercial License"
						price="Tailored Pricing"
						priceSubtext="Starting at €10,000/year"
						features={[
							'No AGPL-3.0 Obligations',
							'Premium Support',
							'Annual Contracts',
							'SLA Options'
						]}
						ctaText="Get Quote"
						ctaLink="https://hyvor.com/enterprise"
					/>
				</div>
			</div>
			<div class="cloud section hds-box">
				<div class="main-title">Cloud</div>
				<div class="plans">
					<Plan
						name="Standard"
						price="€30/month"
						priceSubtext="300,000 emails/month included"
						features={[
							'€1 per additional 10,000 emails',
							'Priority Support',
							'Managed Deliverability',
							'SLA & Uptime Guarantee'
						]}
						ctaText="Get Started"
						ctaLink="#waitlist"
					/>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
	.wrap {
		background-color: white;
		padding: 60px 0;
	}

	h3 {
		font-size: 32px;
		line-height: 40px;
		margin-top: 0;
		margin-bottom: 30px;
	}

	.pricing {
		display: flex;
		gap: 10px;
	}

	.section {
		flex: 1;
		border-radius: 20px;
		overflow: hidden;
	}

	.self-hosted {
		flex: 2;
	}

	.main-title {
		padding: 16px 20px;
		font-size: 20px;
		font-weight: 600;
		color: var(--accent-text);
		background-color: var(--accent);
	}

	.plans {
		display: flex;
	}
</style>
