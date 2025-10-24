<script>
	import Feature from './Feature.svelte';
	import IconCardText from '@hyvor/icons/IconCardText';
	import IconCheckAll from '@hyvor/icons/IconCheckAll';
	import IconSend from '@hyvor/icons/IconSend';
	import IconBriefcase from '@hyvor/icons/IconBriefcase';
	import IconEnvelopeAt from '@hyvor/icons/IconEnvelopeAt';
	import IconCode from '@hyvor/icons/IconCode';
	import IconFileMedical from '@hyvor/icons/IconFileMedical';
	import IconArrowsAngleExpand from '@hyvor/icons/IconArrowsAngleExpand';
	import IconDatabase from '@hyvor/icons/IconDatabase';
	import IconTruck from '@hyvor/icons/IconTruck';
</script>

<div class="hds-container">
	<h3 id="features">Features</h3>

	<div class="features">
		<Feature title="API for Emails" icon={IconCardText}>
			Send emails using a simple API. Supports HTML & plain text emails, attachments, custom
			headers, and more.
		</Feature>
		<Feature title="...and Everything Else" icon={IconCheckAll}>
			Manage domains, suppressions, and more using the API. Keys management, scopes &
			permissions, and idempotency are included.
		</Feature>
		<Feature title="Webhooks for Events" icon={IconSend}>
			Receive notifications for email events like accepted, bounced, suppression added, and
			more.
		</Feature>
		<Feature title="Bounce & Complaint Handling" icon={IconBriefcase}>
			Hyvor Relay automatically handles bounces and complaints.
		</Feature>
		<Feature title="Email Logs" icon={IconEnvelopeAt}>
			Detailed logs of sent emails including status, headers, SMTP conversations, and more. 30
			days of retention.
		</Feature>
	</div>

	<h3 id="features">Self-Hosting</h3>

	<div class="features">
		<Feature title="Open-Source" icon={IconCode}>
			Hyvor Relay is open-source and licensed under AGPL-3.0. It is built for easy
			self-hosting.
		</Feature>
		<Feature title="Health Checks" icon={IconFileMedical}>
			Monitor the health of your installation, IPs, and domains with built-in health checks.
		</Feature>
		<Feature title="Scalable" icon={IconArrowsAngleExpand}>
			Desined to scale across multiple servers using Docker Swarm. Zero downtime scaling.
		</Feature>
		<Feature title="Deliverability" icon={IconTruck}>
			Deliverability is not an afterthought. Easy multiple IPs setup, queue separation, and
			dedicated IPs support.
		</Feature>
		<Feature title="In-built DNS Server" icon={IconDatabase}>
			A simple DNS server to manage forward DNS, MX, SPF, DKIM records of your installation in
			highly dynamic environments.
		</Feature>
	</div>
</div>

<style>
	h3 {
		font-size: 32px;
		line-height: 40px;
		margin-top: 40px;
		margin-bottom: 40px;
	}

	.features {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 24px;
	}
</style>
