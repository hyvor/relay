<script>
	import { Hero, FullTrialSignup } from '@hyvor/design/marketing';
	import FAQ from './FAQ.svelte';
	import Features from './Features.svelte';
	import Enterprise from './Enterprise.svelte';
	import JoinWaitlist from './JoinWaitlist.svelte';
</script>

<svelte:head>
	<title>Hyvor Relay</title>
	<meta
		name="description"
		content="Open-Source Email API for Developers. Self-hosted Alternative to SES, Mailgun, SendGrid."
	/>
	<link rel="canonical" href="https://relay.hyvor.com" />
</svelte:head>

<Hero
	badge="Open-Source & Self-Hosted"
	title="Hyvor Relay"
	subtitle="Email API for Developers"
	fullHeight
	buttons={[
		{ href: '/hosting', label: 'Start Self-Hosting', size: 'x-large' },
		{ href: '/docs', label: 'Product Docs', size: 'x-large', variant: 'outline' }
	]}
>
</Hero>
<Features />
<Enterprise />
<FAQ />

<FullTrialSignup
	title="Ready to get started?"
	description="Deploy Hyvor Relay on your own infrastructure in minutes, or join the waitlist for Hyvor Relay Cloud."
	button={[
		{ href: '/hosting', label: 'Start Self-Hosting' },
		{ href: '#waitlist', label: 'Join Cloud Waitlist' }
	]}
	checks={['Free & open-source (AGPL-3.0)', 'No credit card required', 'Deploy in minutes']}
/>
