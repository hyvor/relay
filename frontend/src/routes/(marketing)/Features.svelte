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
		<Feature title="Email API" icon={IconEnvelope}>
			Send emails using a simple idempotent API. Supports HTML & plain text emails, multiple
			recipients, attachments, custom headers, etc.
		</Feature>
		<Feature title="Automate Everything" icon={IconGear}>
			A comprehensive scope-based REST API to automate domains, suppressions, API keys, and
			webhooks.
		</Feature>
		<Feature title="Webhooks for Events" icon={IconSend}>
			Receive notifications for email events like accepted, bounced, suppression added, etc.
		</Feature>
		<Feature title="Project Management" icon={IconFiles}>
			Manage multiple projects under a single account. Each project can have its own domains,
			API keys, webhooks, and settings.
		</Feature>
		<Feature title="Domains & DKIM" icon={IconShieldLock}>
			Manage multiple sending domains, each secured with mandatory DKIM signing.
		</Feature>
		<Feature title="Bounce & Complaint Handling" icon={IconEnvelopeExclamation}>
			Hyvor Relay automatically handles bounces and complaints with a managed suppression
			list.
		</Feature>
		<Feature title="Email Logs" icon={IconCardList}>
			View detailed logs of sent emails including status, headers, SMTP conversations, and
			more. 30 days of retention.
		</Feature>
	</div>

	<h3 id="features">Self-Hosting</h3>

	<div class="features">
		<Feature title="Open-Source" icon={IconCode}>
			Hyvor Relay is open-source and licensed under AGPL-3.0. It is built for easy
			self-hosting.
		</Feature>
		<Feature title="Commercial Support" icon={IconBuilding}>
			Optionally, get commercial support from the HYVOR team for self-hosted installations for
			enterprise use-cases.
		</Feature>
		<Feature title="Deliverability" icon={IconTruck}>
			SPF, reverse DNS, mult-IPs setup, queue separation, and more best practices are
			built-in.
		</Feature>
		<Feature title="Health Checks" icon={IconActivity}>
			Get notified immediately on server, DNS, IP reputation, or other issues that impact
			deliverability.
		</Feature>
		<Feature title="Scalability" icon={IconArrowsAngleExpand}>
			Designed to scale horizontally in cloud or on-premise environments using Docker Swarm.
		</Feature>
		<Feature title="In-built DNS Server" icon={IconDatabase}>
			A DNS server that automates forward DNS, MX, SPF, DKIM records. No need to manually
			configure DNS records.
		</Feature>
		<Feature title="Observability" icon={IconGraphUp}>
			Integrates with Prometheus and Grafana for monitoring, alerting, and log aggregation.
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
</style>
