<script>
	import { FeatureSplit, SpotlightSplit, AllFeaturesAccordion } from '@hyvor/design/marketing';
	import IconGear from '@hyvor/icons/IconGear';
	import IconSend from '@hyvor/icons/IconSend';
	import IconEnvelopeExclamation from '@hyvor/icons/IconEnvelopeExclamation';
	import IconFiles from '@hyvor/icons/IconFiles';
	import IconCode from '@hyvor/icons/IconCode';
	import IconBuilding from '@hyvor/icons/IconBuilding';
	import IconDatabase from '@hyvor/icons/IconDatabase';
	import IconActivity from '@hyvor/icons/IconActivity';
	import IconTruck from '@hyvor/icons/IconTruck';
	import IconGraphUp from '@hyvor/icons/IconGraphUp';

	const categories = [
		{
			label: 'Sending',
			icon: IconSend,
			color: 'var(--green)',
			features: [
				{
					icon: IconGear,
					title: 'Automate Everything',
					description:
						'Control your email sending with a powerful, scope-based REST API. Automate domain setup, suppressions, API keys, and webhooks in seconds.'
				},
				{
					icon: IconSend,
					title: 'Webhooks for Events',
					description:
						'Stay in sync with real-time event webhooks. Get notified on deliveries, bounces, domain verifications, and more.'
				},
				{
					icon: IconEnvelopeExclamation,
					title: 'Bounce & Complaint Handling',
					description:
						'Stop worrying about bad addresses. Hyvor Relay automatically manages bounces, complaints, and suppressions for you.'
				},
				{
					icon: IconFiles,
					title: 'Project Management',
					description:
						'Organize your work the developer way. Create isolated projects, each with its own domains, keys, and webhooks.'
				}
			]
		},
		{
			label: 'Self-Hosting',
			icon: IconCode,
			color: 'var(--blue)',
			features: [
				{
					icon: IconCode,
					title: 'Open-Source',
					description:
						'Fully open-source under AGPL-3.0. Deploy, inspect, and modify Hyvor Relay on your own infrastructure with zero vendor lock-in.'
				},
				{
					icon: IconBuilding,
					title: 'Commercial Support',
					description:
						'Self-hosted, enterprise-ready. Get priority updates and expert support directly from the HYVOR team with an enterprise license.'
				},
				{
					icon: IconDatabase,
					title: 'In-built DNS Server',
					description:
						'Hyvor Relay comes with a DNS server that handles DNS queries for the instance domain and its subdomains — DNS automation, done right.'
				},
				{
					icon: IconActivity,
					title: 'Health Checks',
					description:
						'One of the unique features of Hyvor Relay: built-in health checks for all components to ensure smooth operation.'
				}
			]
		},
		{
			label: 'Reliability & Observability',
			icon: IconGraphUp,
			features: [
				{
					icon: IconTruck,
					title: 'Deliverability',
					description:
						'Deliverability is the ability of an email to reach the inbox without being blocked or marked as spam. SPF, reverse DNS, multiple IPs, and queue isolation - baked in from day one.'
				},
				{
					icon: IconGraphUp,
					title: 'Observability',
					description:
						'Industry-grade monitoring with built-in Prometheus metrics and a pre-configured Grafana dashboard for performance, alerts, and logs.'
				}
			]
		}
	];
</script>

<FeatureSplit
	eyebrow="Email API"
	title="Send reliable emails with a clean REST API"
	description="Send reliable emails with a clean, idempotent REST API — then see exactly what happened to every message, with full delivery logs and SMTP traces."
	bullets={[
		'HTML and plain text, attachments, custom headers, multiple recipients',
		'Idempotent sends prevent accidental duplicate emails',
		'30 days of full delivery logs, including SMTP traces'
	]}
	button={{ href: '/docs/send-emails', label: 'Read the API docs' }}
>
	{#snippet visual()}
		<div class="hds-box screenshot">
			<img
				src="/img/docs/intro-sends.png"
				alt="Email delivery log with SMTP trace in Hyvor Relay"
			/>
		</div>
	{/snippet}
</FeatureSplit>

<FeatureSplit
	eyebrow="Domains"
	title="Send from any domain, fully verified"
	description="Hyvor Relay automates DKIM, SPF, PTR, and the other DNS records required for SMTP & TLS, so every domain you add is cryptographically signed and verifiable."
	bullets={[
		'DKIM signing enforced on every domain',
		'SPF and PTR records configured automatically',
		'Verify ownership with a single DNS TXT record'
	]}
	button={{ href: '/docs/domains', label: 'Read the docs' }}
	flip
>
	{#snippet visual()}
		<div class="hds-box screenshot">
			<img
				src="/img/docs/domains-dns.png"
				alt="DKIM DNS record verification modal in Hyvor Relay"
			/>
		</div>
	{/snippet}
</FeatureSplit>

<SpotlightSplit
	title="Built to scale on your own infrastructure."
	description="Designed to scale horizontally by adding more servers and workers. Deploy with Docker Compose or Docker Swarm — Hyvor Relay load balances email sending across every app server."
	stats={['Horizontal scaling', 'Docker Swarm ready']}
	button={{ href: '/hosting/scaling', label: 'Learn about scaling' }}
	background="#243660"
	highlightColor="rgba(200, 220, 255, 0.9)"
>
	{#snippet content()}
		<div class="hds-box arch-visual">
			<img
				src="/img/docs/intro-arch.png"
				alt="Hyvor Relay self-hosted architecture diagram"
			/>
		</div>
	{/snippet}
</SpotlightSplit>

<AllFeaturesAccordion
	description="Everything else you need to run production email at scale."
	{categories}
/>

<style>
	.screenshot {
		padding: 12px;
		overflow: hidden;
	}

	.screenshot img {
		display: block;
		width: 100%;
		border-radius: 8px;
	}

	.arch-visual {
		width: 460px;
		max-width: 100%;
		padding: 20px;
		box-sizing: border-box;
	}

	.arch-visual img {
		display: block;
		width: 100%;
	}
</style>
