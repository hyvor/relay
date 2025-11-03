<script>
	import Feature from './Feature.svelte';
	import IconSend from '@hyvor/icons/IconSend';
	import IconCode from '@hyvor/icons/IconCode';
	import IconArrowsAngleExpand from '@hyvor/icons/IconArrowsAngleExpand';
	import IconDatabase from '@hyvor/icons/IconDatabase';
	import IconTruck from '@hyvor/icons/IconTruck';
	import IconEnvelope from '@hyvor/icons/IconEnvelope';
	import IconEnvelopeExclamation from '@hyvor/icons/IconEnvelopeExclamation';
	import IconCardList from '@hyvor/icons/IconCardList';
	import IconGear from '@hyvor/icons/IconGear';
	import IconFiles from '@hyvor/icons/IconFiles';
	import IconShieldLock from '@hyvor/icons/IconShieldLock';
	import IconBuilding from '@hyvor/icons/IconBuilding';
	import IconActivity from '@hyvor/icons/IconActivity';
	import IconGraphUp from '@hyvor/icons/IconGraphUp';
</script>

<div class="hds-container">
	<h3 id="features">Features</h3>

	<div class="features">
		<Feature title="Email API" icon={IconEnvelope} docsLink="/docs/send-emails">
			Send reliable emails with a clean, idempotent REST API. Supports HTML and plain text,
			attachments, custom headers, and multiple recipients.
		</Feature>
		<Feature title="Automate Everything" icon={IconGear} docsLink="/docs/api-console">
			Control your email sending with a powerful, scope-based REST API. Automate domain setup,
			suppressions, API keys, and webhooks in seconds.
		</Feature>
		<Feature title="Webhooks for Events" icon={IconSend} docsLink="/docs/webhooks">
			Stay in sync with real-time event webhooks. Get notified on deliveries, bounces, domain
			verifications, and more.
		</Feature>
		<Feature title="Domains & DKIM" icon={IconShieldLock} docsLink="/docs/domains">
			Send from any domain with confidence. Manage multiple domains with enforced DKIM signing
			for maximum deliverability and trust.
		</Feature>
		<Feature title="Project Management" icon={IconFiles}>
			Organize your work the developer way. Create isolated projects, each with its own
			domains, keys, and webhooks. All under one account.
		</Feature>
		<Feature title="Bounce & Complaint Handling" icon={IconEnvelopeExclamation}>
			Stop worrying about bad addresses. Hyvor Relay automatically manages bounces,
			complaints, and suppressions for you.
		</Feature>
		<Feature title="Email Logs" icon={IconCardList}>
			See everything that happens after “Send.” Search full delivery logs (headers, status,
			and SMTP traces) with 30 days of retention.
		</Feature>
	</div>

	<h3 id="features">Self-Hosting</h3>

	<div class="features">
		<Feature
			title="Open-Source"
			icon={IconCode}
			docsLink="https://github.com/hyvor/relay"
			docsText="View on GitHub"
			docsBlank={true}
		>
			Fully open-source under AGPL-3.0. Built for developers who want control. Deploy,
			inspect, and modify Hyvor Relay on your own infrastructure with zero vendor lock-in.
		</Feature>
		<Feature
			title="Commercial Support"
			icon={IconBuilding}
			docsLink="https://hyvor.com/hosting-license"
			docsText="Why an Enterprise License?"
			docsBlank={true}
		>
			Self-hosted, enterprise-ready. Get priority updates and expert support directly from the
			HYVOR team with an enterprise license.
		</Feature>
		<Feature
			title="Deliverability"
			icon={IconTruck}
			docsLink="/hosting/deliverability"
			docsText="Learn more"
		>
			Deliver every email that matters. SPF, reverse DNS, multiple IPs, queue isolation -
			industry best practices baked in from day one.
		</Feature>
		<Feature title="Health Checks" icon={IconActivity}>
			Know before anything breaks. Get instant alerts for server issues, DNS errors, or IP
			reputation drops so you can fix them fast.
		</Feature>
		<Feature title="Scalability" icon={IconArrowsAngleExpand} docsLink="/hosting/scaling">
			Built to grow with your workloads. Scale horizontally on any cloud or on-prem setup with
			Docker Swarm.
		</Feature>
		<Feature title="In-built DNS Server" icon={IconDatabase}>
			DNS automation, done right. Relay includes an integrated DNS server that manages forward
			records, MX, SPF, and DKIM - no manual setup required.
		</Feature>
		<Feature title="Observability" icon={IconGraphUp} docsLink="/hosting/monitoring">
			Visibility for every metric that matters. Native integrations with Prometheus and
			Grafana let you monitor performance, alerts, and logs in one place.
		</Feature>
	</div>
</div>

<style>
	h3 {
		font-size: 32px;
		line-height: 40px;
		margin-top: 60px;
		margin-bottom: 40px;
	}

	.features {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 24px;
	}

	/*	mobile style */
	@media (max-width: 976px) {
		.features {
			grid-template-columns: 1fr;
			justify-content: center;
		}

		h3 {
			text-align: center;
		}
	}
</style>
