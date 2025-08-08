<script>
	import { DocsImage } from '@hyvor/design/marketing';
</script>

<h1>Domains</h1>

<p>
	Hyvor Relay requires that all emails are authenticated using DKIM. Therefore, you need to first
	configure the domains of the email addresses you want to send emails from (FROM address). This
	is a one-time setup for each domain in a project. Once you configure a domain, you can send
	emails from any email address under that domain without any additional configuration.
</p>

<p>
	To verify your domain, you need to add a TXT record to your domain's DNS settings. Hyvor Relay
	uses this TXT record to verify that you own the domain. Email providers use this TXT record for
	DKIM verification.
</p>

<ul>
	<li>
		<a href="#domains-in-console">Configuring Domains in the Console</a>
	</li>
	<li>
		<a href="#status">Domain Status</a>
	</li>
	<li>
		<a href="#domains-in-api">Automating Domains using the API</a>
	</li>
	<li>
		<a href="#faqs">FAQs</a>
	</li>
</ul>

<h2 id="domains-in-console">Configuring Domains in the Console</h2>

<p>You can add domains in the Hyvor Relay Console:</p>

<DocsImage src="/img/docs/intro-domain.png" alt="Add Domain in Hyvor Relay" />

<p>Then, you will see the instructions to add a TXT record to your domain's DNS settings.</p>

<DocsImage src="/img/docs/domains-dns.png" alt="Add Domain in Hyvor Relay" />

<p>
	Once you add the TXT record, click the <strong>Verify</strong> button. It might
	<a href="#how-long-to-verify">take a few minutes</a> for the verification to complete. Once verified,
	you can start sending emails from this domain.
</p>

<p>Make sure to keep the TXT record in your domain's DNS settings.</p>

<h2 id="status">Domain Status</h2>

<p>A domain can be in one of the following states:</p>

<ul>
	<li>
		<strong style="color:var(--text-light)">Unverified</strong>: The domain is added, but the
		TXT record is not verified yet. You cannot send emails from this domain until it is
		verified. You have 14 days to verify the domain, or it will be automatically removed.
	</li>
	<li>
		<strong style="color:var(--green)">Verified</strong>: The domain is verified, and you can
		send emails from this domain.
	</li>
	<li>
		<strong style="color:var(--orange)">Warning</strong>: The domain is put on warning status.
		This can happen if the TXT record is removed or changed. You can still send emails from this
		domain, but you must resolve the issue within 24 hours, or the domain will be marked as
		unverified.
	</li>
	<li>
		<strong style="color:var(--red)">Banned</strong>: The domain is banned from sending emails.
		This can happen if the domain is flagged for spam, abuse, or other issues. You cannot send
		emails from this domain until the issue is resolved. Contact support to resolve the issue.
	</li>
</ul>

<h2 id="domains-in-api">Automating Domains using the API</h2>

<p>
	Manually configuring domains might not be feasible if you have a large number of domains or if
	you allow your users to send emails using their own domains. In such cases, use the <a
		href="/docs/api-console">Console API</a
	>
	to automate domain configuration. See
	<a href="/docs/api-console#domains">Domains in Console API</a> to get started.
</p>

<p>
	Note that the verification process is asynchronous. You need to poll the <code
		>GET /domains/:id</code
	>
	or
	<code>GET /domains/:domain</code> endpoint periodically to check the status of the domain
	verification. Or, you can use the <code>domain.verified</code>
	<a href="/docs/webhooks">webhook</a>
	to get notified when the domain is verified.
</p>

<h2 id="faqs">FAQs</h2>

<p>
	<strong>What DNS records should I add?</strong> <br />
	Only one: a DKIM TXT record. The exact value of the TXT record is provided in the Hyvor Relay Console
	when you add a domain. The hostname is unique for the project and is in the format
	<code>rly20250709021031291c6964._domainkey.example.com</code>
</p>

<p id="how-long-to-verify">
	<strong> How long does it take to verify a domain?</strong> <br />
	After adding the TXT record to your domain's DNS settings, it will take a few minutes in most cases.
	In some cases, it may take up to 24 hours for the DNS changes to propagate. You can check the status
	of the domain in the Hyvor Relay Console or using the Console API. We run a verification check every
	5 minutes. You will also receive an email notification when the domain is verified.
</p>

<p>
	<strong>Can I use the same domain in multiple projects?</strong> <br />
	Yes, however, you need to add a separate TXT record for each project since the DKIM selector is unique
	for each project.
</p>

<p>
	<strong>What happens if I delete a domain?</strong> <br />
	Deleting a domain will stop all sending activity from that domain and remove all associated credentials,
	DNS verification status, and sending logs. This action is irreversible.
</p>

<p>
	<strong>Can I use emails of a subdomain of the configured domain?</strong> <br />
	No, currently you cannot use emails of a subdomain (info@subdomain.example.com) of the configured
	domain (example.com). You need to configure each subdomain separately.
</p>

<!-- <p>
	<strong>Can I use a custom DKIM selector?</strong> <br />
	Yes, please contact us to set up a custom DKIM selector for your project.
</p> -->
