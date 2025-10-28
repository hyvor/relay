<script>
	import { Button, Tag } from '@hyvor/design/components';
	import FAQ from './FAQ.svelte';
	import IconCode from '@hyvor/icons/IconCode';
	import IconHouse from '@hyvor/icons/IconHouse';
	import Features from './Features.svelte';
	import Pricing from './Pricing.svelte';
	import JoinWaitlist from './JoinWaitlist.svelte';
</script>

<svelte:head>
	<title>Hyvor Relay</title>
	<meta name="description" content="Alternative to AWS SES & Mailgun" />
	<link rel="canonical" href="https://post.hyvor.com" />
</svelte:head>

<div class="hds-container wrap">
	<section class="main-box">
		<div class="left">
			<h1>Hyvor Relay</h1>
			<h2>Email API for Developers</h2>

			<div class="tags">
				<Tag color="green">
					{#snippet start()}
						<IconCode />
					{/snippet}
					Open-Source AGPL-3.0
				</Tag>
				<Tag color="blue">
					{#snippet start()}
						<IconHouse size={12} />
					{/snippet}
					Self-Hosted
				</Tag>
			</div>

			<div class="button-wrap">
				<!-- <Tooltip
					text="Cloud public release is planned for 2026. You can self-host Hyvor Relay now."
				>
					<Button disabled size="large">Sign up</Button>
				</Tooltip> -->
				<Button as="a" href="/hosting" size="large">Start Self-Hosting</Button>
				<Button as="a" href="/docs" size="large" variant="outline">Product Docs</Button>
			</div>
		</div>

		<div class="right">
			<JoinWaitlist />
		</div>
	</section>
</div>

<Pricing />
<Features />
<FAQ />

<style>
	section {
		padding: 60px 0 100px;
		display: flex;
		flex-direction: row;
		gap: 40px;
	}

	.left {
		width: 50%;
	}

	.left .button-wrap {
		margin-top: 20px;
		display: block;
	}
	.right {
		width: 50%;
	}

	h1 {
		font-size: 48px;
		line-height: 60px;
		margin-top: 0;
		margin-bottom: 12px;
	}

	h2 {
		font-size: 28px;
		line-height: 32px;
		margin-top: 12px;
		margin-bottom: 18px;
		color: var(--text-light);
	}

	.tags {
		margin-bottom: 40px;
	}

	.button-wrap {
		display: flex;
		gap: 12px;
	}

	/*	media queries for 976px*/
	@media (max-width: 976px) {
		.wrap {
		}
		section {
			flex-direction: column;
			padding: 40px 0 60px;
		}

		.left,
		.right {
			width: 100%;
		}

		.left {
			text-align: center;
		}

		.right {
			margin: 40px auto;
		}

		h1 {
			font-size: 36px;
			line-height: 44px;
		}

		h2 {
			font-size: 24px;
			line-height: 28px;
		}

		.button-wrap {
			flex-direction: column;
			gap: 12px;
		}
	}
</style>
