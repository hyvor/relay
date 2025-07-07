<script>
	import { Button, Tag, Tooltip } from '@hyvor/design/components';
	import Letter from './Letter.svelte';
	import IconCode from '@hyvor/icons/IconCode';
	import IconHouse from '@hyvor/icons/IconHouse';
	import Features from './Features.svelte';
</script>

<svelte:head>
	<title>Hyvor Relay</title>
	<meta name="description" content="Alternative to AWS SES & Mailgun" />
	<link rel="canonical" href="https://post.hyvor.com" />
</svelte:head>

<div class="hds-container">
	<section>
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
			<Tooltip text="Cloud version is planned for 2026. You can self-host Hyvor Relay now.">
				<Button disabled size="large">Sign up</Button>
			</Tooltip>
			<Button as="a" href="/hosting" size="large" color="gray">Self-Host Now</Button>
		</div>
	</section>
	<Features />
	<!-- <Letter /> -->
</div>

<style>
	section {
		padding: 60px 0 100px;
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
</style>
