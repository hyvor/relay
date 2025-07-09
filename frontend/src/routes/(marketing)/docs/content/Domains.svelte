<h1>Domains</h1>

<p>
	Hyvor Relay requires that all emails are authenticated using DKIM. Therefore, you need to first
	configure the domains of the email addresses you want to send emails from (FROM address). This
	is a one-time setup for each domain. Once you configure a domain, you can send emails from any
	email address under that domain without any additional configuration.
</p>

<p>
	To verify your domain, you need to add a TXT record to your domain's DNS settings. We use this
	TXT record to verify that you own the domain. Email providers use this TXT record for DKIM
	verification.
</p>

<ul>
	<li>
		<a href="#domains-in-console">Configuring Domains in the Console</a>
	</li>
	<li>
		<a href="#domains-in-api">Automating Domains using the API</a>
	</li>
	<li>
		<a href="#faqs">FAQs</a>
	</li>
</ul>

<h2 id="domains-in-console">Configuring Domains in the Console</h2>

<p>You can add domains in the Hyvor Relay Console.</p>

<h2 id="domains-in-console">Automating Domains using the API</h2>

<p>
	You can also use the <a href="/docs/api-console">Console API</a> to automate domain
	configuration. This is useful if you allow your users to send emails using their own domains or
	if you have a large number of domains to configure. See
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

<p>
	<strong> How long does it take to verify a domain?</strong> <br />
	After adding the TXT record to your domain's DNS settings, it may take a few minutes in most cases.
	In some cases, it may take up to 24 hours for the DNS changes to propagate. You can check the status
	of the domain in the Hyvor Relay Console or using the Console API. We run a verification check every
	5 minutes.
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
	<strong>Can I use emails of the subdomains of the configured domain?</strong> <br />
	No, currently you cannot use emails of the subdomains of the configured domain. You need to configure
	each subdomain separately.
</p>

<p>
	<strong>Can I use a custom DKIM selector?</strong> <br />
	Yes, please contact us to set up a custom DKIM selector for your project.
</p>
