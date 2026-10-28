<script>
	import Plan from './Plan.svelte';
</script>

<svelte:head>
	<title>Pricing - Hyvor Relay</title>
	<meta
		name="description"
		content="Hyvor Relay pricing: self-host for free under AGPL-3.0 or with an Enterprise license, or use the fully managed Hyvor Relay Cloud."
	/>
	<link rel="canonical" href="https://relay.hyvor.com/pricing" />
</svelte:head>

<div class="hds-container">
	<div class="head">
		<h1>Pricing</h1>
		<p>Self-host for free, get commercial support, or let us run it for you in the cloud.</p>
	</div>

	<section class="tier">
		<div class="tier-head">
			<h2>Self-Hosting</h2>
			<p>
				Deploy Hyvor Relay on your own infrastructure — free and open-source, or with
				commercial support.
			</p>
		</div>
		<div class="hds-box plans">
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
				name="Enterprise License"
				price="Tailored Pricing"
				priceSubtext="Starting at €10,000/year"
				features={[
					'No AGPL-3.0 Obligations',
					'Volume-Based Pricing',
					'Enterprise Support',
					'SLA Options'
				]}
				ctaText="Get Quote"
				ctaLink="https://hyvor.com/enterprise"
			/>
		</div>
	</section>

	<section class="tier">
		<div class="tier-head">
			<h2>Cloud License</h2>
			<p>Fully managed Hyvor Relay, hosted by HYVOR — no infrastructure to run.</p>
		</div>
		<div class="hds-box plans cloud-plans">
			<Plan
				name="Standard"
				price="€30/month*"
				priceSubtext="300,000 emails/month included"
				features={[
					'€1 per additional 10,000 emails',
					'Priority Support',
					'Managed Deliverability',
					'SLA & Uptime Guarantee'
				]}
				ctaText="Join Waitlist"
				ctaLink="/#waitlist"
			/>
		</div>
		<div class="star">* Cloud pricing is estimated and will be finalized at launch.</div>
	</section>
</div>

<style>
	.head {
		text-align: center;
		padding: 60px 0 40px;
	}

	h1 {
		font-size: 44px;
		line-height: 52px;
		margin: 0 0 12px;
	}

	.head p {
		font-size: 18px;
		color: var(--text-light);
		margin: 0;
	}

	.tier {
		padding-bottom: 60px;
	}

	.tier-head {
		margin-bottom: 20px;
	}

	.tier-head h2 {
		font-size: 26px;
		margin: 0 0 6px;
	}

	.tier-head p {
		font-size: 16px;
		color: var(--text-light);
		margin: 0;
	}

	.plans {
		display: flex;
		border-radius: 20px;
		overflow: hidden;
	}

	.cloud-plans {
		max-width: 420px;
	}

	.star {
		font-size: 14px;
		color: var(--text-light);
		margin-top: 20px;
	}

	/* mobile styles 976px */
	@media (max-width: 976px) {
		.head {
			padding: 40px 0 30px;
		}

		h1 {
			font-size: 32px;
			line-height: 40px;
		}

		.tier-head {
			text-align: center;
		}

		.plans {
			flex-direction: column;
		}

		.cloud-plans {
			max-width: 100%;
		}
	}
</style>
